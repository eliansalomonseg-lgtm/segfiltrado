import csv
import calendar
import json
import math
import re
import sys
import unicodedata
from pathlib import Path

import pandas as pd

sys.stdout.reconfigure(encoding="utf-8")

TARIFAS_MENSUALES = {"03", "68", "78"}
TARIFAS_BIMESTRALES = {"01", "02", "1A", "1B", "1C", "1E"}


def normalizar(valor):
    if valor is None or pd.isna(valor):
        return ""
    texto = unicodedata.normalize("NFKD", str(valor))
    texto = "".join(letra for letra in texto if not unicodedata.combining(letra))
    return re.sub(r"\s+", " ", re.sub(r"[^A-Z0-9 ]", " ", texto.upper())).strip()


def normalizar_columna(valor):
    return normalizar(valor).replace(" ", "")


def limpiar(valor):
    if valor is None or pd.isna(valor):
        return ""
    if isinstance(valor, float) and math.isfinite(valor) and valor.is_integer():
        return str(int(valor))
    return str(valor).strip()


def numero(valor):
    if valor is None or pd.isna(valor):
        return 0.0
    if isinstance(valor, (int, float)):
        return float(valor)
    texto = str(valor).replace("$", "").replace(",", "").strip()
    if texto == "":
        return 0.0
    try:
        return float(texto)
    except ValueError:
        return 0.0


def fecha(valor):
    if valor is None or pd.isna(valor):
        return None
    convertido = pd.to_datetime(valor, errors="coerce")
    if pd.isna(convertido):
        return None
    return convertido.to_pydatetime()


def buscar_cabecera(ruta):
    crudos = pd.read_excel(ruta, header=None, dtype=object)
    for indice, fila in crudos.iterrows():
        valores = [normalizar_columna(valor) for valor in fila.values]
        if "RPU" in valores and "TOTAL" in valores and "DESDE" in valores and "HASTA" in valores:
            return indice
    raise ValueError("No se encontro la cabecera del reporte CFE.")


def cargar_reporte(ruta):
    cabecera = buscar_cabecera(ruta)
    datos = pd.read_excel(ruta, header=cabecera, dtype=object)
    datos.columns = [normalizar_columna(columna) for columna in datos.columns]
    requeridas = ["RPU", "NOMBRE", "POBLACION", "TARIFA", "DESDE", "HASTA", "CONSUMO", "ENERGIA", "DAP", "CARGOSYDEPOSITOS", "CREDITOSYREDONDEOS", "TOTAL", "DIFERENCIA"]
    faltantes = [columna for columna in requeridas if columna not in datos.columns]
    if faltantes:
        raise ValueError("Faltan columnas del reporte CFE: " + ", ".join(faltantes))
    datos = datos[datos["RPU"].notna()].copy()
    datos["RPU"] = datos["RPU"].map(limpiar)
    datos = datos[datos["RPU"].str.fullmatch(r"\d{8,15}", na=False)]
    return datos


def mes_desde_nombre(ruta):
    coincidencia = re.search(r"(20\d{2})[-_](\d{2})", ruta.name)
    if not coincidencia:
        return None
    return int(coincidencia.group(1)), int(coincidencia.group(2))


def periodo_esperado(tarifa, modo):
    tarifa_normalizada = normalizar_columna(tarifa)
    if modo == "mensual":
        return "mensual", 25, 35
    if modo == "bimestral":
        return "bimestral", 50, 75
    if tarifa_normalizada in TARIFAS_MENSUALES:
        return "mensual", 25, 35
    if tarifa_normalizada in TARIFAS_BIMESTRALES:
        return "bimestral", 50, 75
    return "bimestral", 50, 75


def rango_dias_periodo(tipo_periodo, mes_reporte):
    if not mes_reporte:
        if tipo_periodo == "mensual":
            return 25, 35, "mensual"
        return 50, 75, "bimestral"
    anio, mes = mes_reporte
    if tipo_periodo == "mensual":
        dias_mes = calendar.monthrange(anio, mes)[1]
        return max(1, dias_mes - 2), dias_mes + 2, f"mensual de {dias_mes} dias"
    mes_anterior = 12 if mes == 1 else mes - 1
    anio_anterior = anio - 1 if mes == 1 else anio
    dias_bimestre = calendar.monthrange(anio_anterior, mes_anterior)[1] + calendar.monthrange(anio, mes)[1]
    return max(1, dias_bimestre - 3), dias_bimestre + 3, f"bimestral de {dias_bimestre} dias"


def clasificar_alertas(fila, mes_reporte, modo_periodo):
    desde = fecha(fila["DESDE"])
    hasta = fecha(fila["HASTA"])
    tipo_periodo, _, _ = periodo_esperado(fila["TARIFA"], modo_periodo)
    minimo_dias, maximo_dias, descripcion_periodo = rango_dias_periodo(tipo_periodo, mes_reporte)
    consumo = numero(fila["CONSUMO"])
    energia = numero(fila["ENERGIA"])
    dap = numero(fila["DAP"])
    cargos = numero(fila["CARGOSYDEPOSITOS"])
    creditos = numero(fila["CREDITOSYREDONDEOS"])
    total = numero(fila["TOTAL"])
    diferencia = numero(fila["DIFERENCIA"])
    alertas = []
    severidad = 0
    dias = None

    if desde and hasta:
        dias = (hasta - desde).days
        if dias < minimo_dias or dias > maximo_dias:
            alertas.append(f"Periodo no coincide con {descripcion_periodo} ({dias} dias)")
            severidad += 3
        if mes_reporte and (hasta.year, hasta.month) != mes_reporte:
            alertas.append("Fecha HASTA fuera del mes del reporte")
            severidad += 2
    else:
        alertas.append("Fechas DESDE/HASTA incompletas")
        severidad += 2

    if abs(cargos) >= 1:
        alertas.append("Cargo o deposito aplicado")
        severidad += 3

    if abs(creditos) >= 1:
        alertas.append("Credito o redondeo aplicado")
        severidad += 1

    if abs(diferencia) >= 1:
        alertas.append("Diferencia contra validacion")
        severidad += 3

    if consumo == 0 and total > 80:
        alertas.append("Cobro con consumo cero")
        severidad += 3

    if energia > 0 and dap > energia * 0.65:
        alertas.append("DAP alto contra energia")
        severidad += 1

    if total >= 20000:
        alertas.append("Total elevado")
        severidad += 2

    if consumo >= 3500:
        alertas.append("Consumo elevado")
        severidad += 2

    return alertas, severidad, dias, desde, hasta, consumo, energia, dap, cargos, creditos, total, diferencia, tipo_periodo


def analizar(ruta, anio=None, mes=None, modo_periodo="automatico"):
    datos = cargar_reporte(ruta)
    mes_reporte = (anio, mes) if anio and mes else mes_desde_nombre(ruta)
    registros = []
    total_alertas = 0
    severos = 0
    periodo_correcto = 0

    for _, fila in datos.iterrows():
        alertas, severidad, dias, desde, hasta, consumo, energia, dap, cargos, creditos, total, diferencia, tipo_periodo = clasificar_alertas(fila, mes_reporte, modo_periodo)
        tipo_periodo, _, _ = periodo_esperado(fila["TARIFA"], modo_periodo)
        minimo_dias, maximo_dias, _ = rango_dias_periodo(tipo_periodo, mes_reporte)
        if dias is not None and minimo_dias <= dias <= maximo_dias:
            periodo_correcto += 1
        if alertas:
            total_alertas += 1
        if severidad >= 4:
            severos += 1
        registros.append({
            "rpu": limpiar(fila["RPU"]),
            "nombre": limpiar(fila["NOMBRE"]),
            "poblacion": limpiar(fila["POBLACION"]),
            "tarifa": limpiar(fila["TARIFA"]),
            "desde": desde.strftime("%Y-%m-%d") if desde else "",
            "hasta": hasta.strftime("%Y-%m-%d") if hasta else "",
            "dias": dias if dias is not None else "",
            "tipo_periodo": tipo_periodo,
            "consumo": consumo,
            "energia": energia,
            "dap": dap,
            "cargos_depositos": cargos,
            "creditos_redondeos": creditos,
            "total": total,
            "diferencia": diferencia,
            "alertas": alertas,
            "severidad": severidad
        })

    registros.sort(key=lambda item: (item["severidad"], item["total"]), reverse=True)
    return {
        "ok": True,
        "archivo": ruta.name,
        "mes_reporte": f"{mes_reporte[0]}-{mes_reporte[1]:02d}" if mes_reporte else "",
        "modo_periodo": modo_periodo,
        "resumen": {
            "registros": len(registros),
            "con_alerta": total_alertas,
            "severos": severos,
            "periodo_bimestral": periodo_correcto,
            "importe_total": round(sum(item["total"] for item in registros), 2)
        },
        "registros": registros
    }


def exportar_csv(resultado, ruta_salida):
    with open(ruta_salida, "w", newline="", encoding="utf-8-sig") as archivo:
        escritor = csv.writer(archivo)
        escritor.writerow(["RPU", "NOMBRE", "POBLACION", "TARIFA", "TIPO_PERIODO", "DESDE", "HASTA", "DIAS", "CONSUMO", "ENERGIA", "DAP", "CARGOS_DEPOSITOS", "CREDITOS_REDONDEOS", "TOTAL", "DIFERENCIA", "SEVERIDAD", "ALERTAS"])
        for fila in resultado["registros"]:
            escritor.writerow([
                fila["rpu"],
                fila["nombre"],
                fila["poblacion"],
                fila["tarifa"],
                fila["tipo_periodo"],
                fila["desde"],
                fila["hasta"],
                fila["dias"],
                fila["consumo"],
                fila["energia"],
                fila["dap"],
                fila["cargos_depositos"],
                fila["creditos_redondeos"],
                fila["total"],
                fila["diferencia"],
                fila["severidad"],
                " | ".join(fila["alertas"])
            ])


try:
    if len(sys.argv) not in {2, 5, 6}:
        raise ValueError("Se requiere un reporte CFE en Excel.")
    ruta_reporte = Path(sys.argv[1])
    anio_reporte = None
    mes_reporte = None
    modo = "automatico"
    ruta_csv = None
    if len(sys.argv) >= 5:
        anio_reporte = int(sys.argv[2])
        mes_reporte = int(sys.argv[3])
        modo = sys.argv[4]
        if modo not in {"automatico", "mensual", "bimestral"}:
            raise ValueError("Modo de periodo no valido.")
        if mes_reporte < 1 or mes_reporte > 12:
            raise ValueError("Mes del reporte no valido.")
    if len(sys.argv) == 6:
        ruta_csv = Path(sys.argv[5])
    resultado = analizar(ruta_reporte, anio_reporte, mes_reporte, modo)
    if ruta_csv is not None:
        exportar_csv(resultado, ruta_csv)
        resultado["csv"] = str(ruta_csv)
    print(json.dumps(resultado, ensure_ascii=False))
except Exception as error:
    print(json.dumps({"ok": False, "error": str(error)}, ensure_ascii=False))
    sys.exit(1)
