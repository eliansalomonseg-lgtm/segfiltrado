import argparse
import json
import math
import re
import sys
import unicodedata
from difflib import SequenceMatcher
from pathlib import Path

import pandas as pd


def normalizar(valor):
    if pd.isna(valor) or valor is None:
        return ""
    texto = unicodedata.normalize("NFKD", str(valor))
    texto = "".join(letra for letra in texto if not unicodedata.combining(letra))
    return re.sub(r"\s+", " ", re.sub(r"[^A-Z0-9 ]", " ", texto.upper())).strip()


def limpiar(valor):
    if pd.isna(valor) or valor is None:
        return None
    if isinstance(valor, float) and math.isfinite(valor) and valor.is_integer():
        return str(int(valor))
    return str(valor).strip()


def preparar_cfe(ruta, periodo):
    datos = pd.read_excel(ruta, dtype=object)
    datos.columns = [normalizar(columna).replace(" ", "_") for columna in datos.columns]
    alias = {
        "RPU": ["RPU"],
        "NOMBRE": ["NOMBRE", "NOMBRE_CFE", "NOMBRE_RECIBO"],
        "POBLACION": ["POBLACION", "POBLACION_CFE"],
        "TARIFA": ["TARIFA", "TARIFA_CFE"],
        "PERIODO_VENCE": ["PERIODO_VENCE", "VENCIMIENTO", "FECHA_VENCIMIENTO"]
    }
    columnas = {}
    for destino, opciones in alias.items():
        columnas[destino] = next((opcion for opcion in opciones if opcion in datos.columns), None)
    if not all(columnas[clave] for clave in ["RPU", "NOMBRE", "POBLACION"]):
        raise ValueError(f"El Excel CFE del periodo {periodo} requiere RPU, NOMBRE y POBLACION")
    salida = pd.DataFrame()
    salida["RPU"] = datos[columnas["RPU"]].map(limpiar)
    salida["NOMBRE"] = datos[columnas["NOMBRE"]].map(limpiar)
    salida["POBLACION"] = datos[columnas["POBLACION"]].map(limpiar)
    salida["TARIFA"] = datos[columnas["TARIFA"]].map(limpiar) if columnas["TARIFA"] else None
    salida["PERIODO_VENCE"] = pd.to_datetime(
        datos[columnas["PERIODO_VENCE"]],
        errors="coerce",
        dayfirst=True
    ) if columnas["PERIODO_VENCE"] else pd.NaT
    salida["PERIODO"] = periodo
    return salida[salida["RPU"].notna() & (salida["RPU"] != "")]


def cargar_escuelas(ruta):
    escuelas = pd.read_json(ruta, dtype=False)
    requeridas = ["CCT", "NOMBRECT", "MUNICIPIO", "NOMBRELOC"]
    faltantes = [columna for columna in requeridas if columna not in escuelas.columns]
    if faltantes:
        raise ValueError(f"Faltan columnas SEG: {', '.join(faltantes)}")
    return escuelas


def registro_precarga(fila):
    fecha = fila["PERIODO_VENCE"]
    return {
        "rpu": limpiar(fila["RPU"]),
        "nombre_cfe": limpiar(fila["NOMBRE"]),
        "poblacion_cfe": limpiar(fila["POBLACION"]),
        "tarifa_cfe": limpiar(fila["TARIFA"]),
        "periodo_vence": fecha.strftime("%Y-%m-%d") if not pd.isna(fecha) else None
    }


def procesar(mes_uno, mes_dos, archivo_escuelas):
    cfe = pd.concat([
        preparar_cfe(mes_uno, "A"),
        preparar_cfe(mes_dos, "B")
    ], ignore_index=True)
    escuelas = cargar_escuelas(archivo_escuelas)
    escuelas["_LOCALIDAD"] = escuelas["NOMBRELOC"].map(normalizar)
    indice = {}
    for _, escuela in escuelas.iterrows():
        localidad = escuela["_LOCALIDAD"]
        if localidad:
            indice.setdefault(localidad, []).append(escuela)
    sugerencias = []
    for rpu, grupo in cfe.groupby("RPU", sort=False):
        referencia = grupo.iloc[-1]
        localidad = normalizar(referencia["POBLACION"])
        candidatos = []
        for escuela in indice.get(localidad, []):
            similitud = SequenceMatcher(
                None,
                normalizar(referencia["NOMBRE"]),
                normalizar(escuela["NOMBRECT"])
            ).ratio() * 100
            candidatos.append({
                "cct": limpiar(escuela["CCT"]),
                "nombre_escuela": limpiar(escuela["NOMBRECT"]),
                "municipio": limpiar(escuela["MUNICIPIO"]),
                "localidad": limpiar(escuela["NOMBRELOC"]),
                "similitud": round(similitud, 2)
            })
        candidatos.sort(key=lambda registro: registro["similitud"], reverse=True)
        sugerencias.append({
            "rpu": limpiar(rpu),
            "nombre_cfe": limpiar(referencia["NOMBRE"]),
            "poblacion_cfe": limpiar(referencia["POBLACION"]),
            "periodos_detectados": int(grupo["PERIODO"].nunique()),
            "opciones": candidatos[:3]
        })
    sugerencias.sort(key=lambda registro: registro["rpu"])
    return {
        "ok": True,
        "precarga": [registro_precarga(fila) for _, fila in cfe.iterrows()],
        "resumen": {
            "registros_precarga": len(cfe),
            "rpu_unicos": int(cfe["RPU"].nunique()),
            "rpu_con_sugerencias": sum(bool(registro["opciones"]) for registro in sugerencias)
        },
        "resultados": sugerencias
    }


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("mes_uno", type=Path)
    parser.add_argument("mes_dos", type=Path)
    parser.add_argument("escuelas", type=Path)
    argumentos = parser.parse_args()
    try:
        print(json.dumps(procesar(argumentos.mes_uno, argumentos.mes_dos, argumentos.escuelas), ensure_ascii=False))
    except Exception as error:
        print(json.dumps({"ok": False, "error": str(error)}, ensure_ascii=False))
        sys.exit(1)


if __name__ == "__main__":
    main()
