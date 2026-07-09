import sys
import json

sys.stdout.reconfigure(encoding="utf-8")

try:
    import math
    import re
    import unicodedata
    import warnings
    from difflib import SequenceMatcher
    from pathlib import Path

    import pandas as pd

    warnings.filterwarnings("ignore")

    MAPA_HOMO = {
        "DCC": ("PREESCOLAR", "Preescolar Indígena"),
        "DDI": ("PREESCOLAR", "Preescolar General"),
        "DJN": ("PREESCOLAR", "Preescolar General"),
        "DCI": ("PRIMARIA", "Primaria Indígena"),
        "DPB": ("PRIMARIA", "Primaria Indígena"),
        "DPR": ("PRIMARIA", "Primaria General"),
        "DST": ("SECUNDARIA", "Secundaria Técnica"),
        "DTV": ("TELESECUNDARIA", "Telesecundaria"),
        "DES": ("SECUNDARIA", "Secundaria General"),
        "DSM": ("SECUNDARIA", "Secundaria General"),
        "DSN": ("SECUNDARIA", "Secundaria General"),
    }

    def normalizar(valor):
        if valor is None or pd.isna(valor):
            return ""
        texto = unicodedata.normalize("NFKD", str(valor))
        texto = "".join(letra for letra in texto if not unicodedata.combining(letra))
        return re.sub(r"\s+", " ", re.sub(r"[^A-Z0-9 ]", " ", texto.upper())).strip()

    def limpiar(valor):
        if valor is None or pd.isna(valor):
            return None
        if isinstance(valor, float) and math.isfinite(valor) and valor.is_integer():
            return str(int(valor))
        return str(valor).strip()

    def normalizar_codigo(valor):
        return normalizar(valor).replace(" ", "")

    def preparar_columnas(datos):
        datos.columns = [normalizar_codigo(columna) for columna in datos.columns]
        return datos

    def filtrar_publico(datos, columna, origen):
        if columna not in datos.columns:
            raise ValueError(f"Faltan columnas {origen}: {columna}")
        return datos[datos[columna].map(normalizar) == "PUBLICO"].copy()

    def mapear_homo(valor):
        homo = normalizar_codigo(valor)
        if homo.startswith("F"):
            return "OFICINA/ZONA ADMINISTRATIVA", "OFICINA/ZONA ADMINISTRATIVA", True
        nivel, subnivel = MAPA_HOMO.get(homo, ("SIN CLASIFICAR", "Sin clasificar"))
        return nivel, subnivel, False

    def aplicar_homo(datos):
        datos["HOMO"] = datos["HOMO"].map(normalizar_codigo)
        mapeos = datos["HOMO"].map(mapear_homo)
        datos["NIVEL"] = [mapeo[0] for mapeo in mapeos]
        datos["SUBNIVEL"] = [mapeo[1] for mapeo in mapeos]
        datos["ADMINISTRATIVO"] = [mapeo[2] for mapeo in mapeos]
        return datos

    def cargar_excel_seg(ruta, columnas):
        datos = preparar_columnas(pd.read_excel(ruta, dtype=object))
        requeridas = ["CCT", "NOMBRECT", "NOMBREMUN", "NOMBRELOC", "STATUS", "HOMO", "SOSTCONTROL"]
        faltantes = [columna for columna in requeridas if columna not in datos.columns]
        if faltantes:
            raise ValueError(f"Faltan columnas SEG: {', '.join(faltantes)}")
        datos = filtrar_publico(datos, "SOSTCONTROL", "SEG")
        datos = aplicar_homo(datos)
        datos["ORIGEN"] = "Catalogo SEG"
        return datos[columnas].copy()

    def cargar_excel_oficializacion(ruta_oficializacion):
        try:
            crudos = pd.read_excel(ruta_oficializacion, header=None, dtype=object)
            datos = None
            columnas_requeridas = ["CVCCT", "NOMBRECT", "CNOMMUN", "CNOMLOC", "HOMO", "CONTROL"]
            for indice, fila in crudos.iterrows():
                valores = [normalizar_codigo(valor) for valor in fila.values]
                if "CVCCT" in valores and "NOMBRECT" in valores:
                    datos = preparar_columnas(pd.read_excel(ruta_oficializacion, header=indice, dtype=object))
                    break
            if datos is None:
                raise ValueError("No se encontro la fila de cabeceras en el archivo de Oficializacion 911.")
            faltantes = [columna for columna in columnas_requeridas if columna not in datos.columns]
            if faltantes:
                raise ValueError(f"Faltan columnas Oficializacion 911: {', '.join(faltantes)}")
        except Exception as e:
            raise ValueError(f"Error al procesar Oficializacion: {str(e)}") from e

        datos = filtrar_publico(datos, "CONTROL", "Oficializacion 911")
        datos = aplicar_homo(datos)
        datos = datos[columnas_requeridas + ["NIVEL", "SUBNIVEL", "ADMINISTRATIVO"]].rename(columns={
            "CVCCT": "CCT",
            "CNOMMUN": "NOMBREMUN",
            "CNOMLOC": "NOMBRELOC",
        })
        datos["STATUS"] = "ACTIVO"
        datos["ORIGEN"] = "Oficializacion 911"
        return datos[["CCT", "NOMBRECT", "NOMBREMUN", "NOMBRELOC", "STATUS", "NIVEL", "SUBNIVEL", "HOMO", "ADMINISTRATIVO", "ORIGEN"]].copy()

    def cargar_excel_cfe(ruta, columnas):
        datos = preparar_columnas(pd.read_excel(ruta, header=2, dtype=object))
        if not all(columna in datos.columns for columna in columnas):
            crudos = pd.read_excel(ruta, header=None, dtype=object)
            fila_cabecera = None
            for indice, fila in crudos.iterrows():
                valores = [normalizar_codigo(valor) for valor in fila.values]
                if "RPU" in valores and "POBLACION" in valores:
                    fila_cabecera = indice
                    break
            if fila_cabecera is not None:
                datos = preparar_columnas(pd.read_excel(ruta, header=fila_cabecera, dtype=object))
        faltantes = [columna for columna in columnas if columna not in datos.columns]
        if faltantes:
            raise ValueError(f"Faltan columnas reales CFE: {', '.join(faltantes)}")
        return datos[columnas].copy()

    def identificar_nivel(nombre):
        texto = normalizar(nombre)
        if any(termino in texto for termino in ["TELE", "TV", "TS"]):
            return "TELESECUNDARIA"
        if any(termino in texto for termino in ["JN", "JARDIN", "KINDER", "PREES"]):
            return "PREESCOLAR"
        if any(termino in texto for termino in ["PRIM", "ESC PRIM", "FED REG"]):
            return "PRIMARIA"
        if any(termino in texto for termino in ["SEC", "TEC", "EST", "GRAL"]):
            return "SECUNDARIA"
        return None

    def coincide_nivel(nivel_cfe, escuela):
        if nivel_cfe is None:
            return False
        nivel = normalizar(escuela["NIVEL"])
        subnivel = normalizar(escuela["SUBNIVEL"])
        if nivel_cfe == "TELESECUNDARIA":
            return nivel == "TELESECUNDARIA" or subnivel == "TELESECUNDARIA"
        return nivel == nivel_cfe

    def esta_activa(status):
        return normalizar(limpiar(status)) in {"1", "ACTIVO", "ACTIVA"}

    def es_administrativa(escuela):
        return bool(escuela["ADMINISTRATIVO"])

    def puntuar(nombre_cfe, escuela):
        similitud = SequenceMatcher(
            None,
            normalizar(nombre_cfe),
            normalizar(escuela["NOMBRECT"])
        ).ratio() * 100
        nivel_cfe = identificar_nivel(nombre_cfe)
        nivel_coincide = coincide_nivel(nivel_cfe, escuela)
        prioridad = (
            (10 if esta_activa(escuela["STATUS"]) else 0)
            + (60 if nivel_coincide else 0)
            - (1000 if es_administrativa(escuela) else 0)
        )
        return similitud, similitud + prioridad, nivel_coincide

    def procesar(ruta_seg, ruta_oficializacion, ruta_cfe_a, ruta_cfe_b=None):
        columnas_seg = ["CCT", "NOMBRECT", "NOMBREMUN", "NOMBRELOC", "STATUS", "NIVEL", "SUBNIVEL", "HOMO", "ADMINISTRATIVO", "ORIGEN"]
        columnas_cfe = ["RPU", "NOMBRE", "DIRECCION", "POBLACION", "TARIFA"]
        catalogo_seg = cargar_excel_seg(ruta_seg, columnas_seg)
        oficializacion = cargar_excel_oficializacion(ruta_oficializacion)
        catalogo_seg["CCT"] = catalogo_seg["CCT"].map(normalizar_codigo)
        oficializacion["CCT"] = oficializacion["CCT"].map(normalizar_codigo)
        seg = pd.concat([catalogo_seg, oficializacion], ignore_index=True)
        seg = seg[seg["CCT"].notna() & (seg["CCT"] != "")]
        seg = seg.drop_duplicates(subset=["CCT"], keep="last")
        cfe_a = cargar_excel_cfe(ruta_cfe_a, columnas_cfe)
        cfe_b = cargar_excel_cfe(ruta_cfe_b, columnas_cfe) if ruta_cfe_b is not None else pd.DataFrame(columns=columnas_cfe)
        cfe = pd.concat([cfe_a, cfe_b], ignore_index=True)
        cfe["RPU"] = cfe["RPU"].map(limpiar)
        cfe = cfe[cfe["RPU"].notna() & (cfe["RPU"] != "")]
        cfe = cfe.drop_duplicates(subset=["RPU"], keep="last").sort_values("RPU")
        indice_localidades = {}
        for _, escuela in seg.iterrows():
            localidad = normalizar(escuela["NOMBRELOC"])
            if localidad:
                indice_localidades.setdefault(localidad, []).append(escuela)
        resultados = []
        for _, medidor in cfe.iterrows():
            opciones = []
            nivel_cfe = identificar_nivel(medidor["NOMBRE"])
            for escuela in indice_localidades.get(normalizar(medidor["POBLACION"]), []):
                similitud, puntuacion, nivel_coincide = puntuar(medidor["NOMBRE"], escuela)
                opciones.append({
                    "cct": limpiar(escuela["CCT"]),
                    "nombre_escuela": limpiar(escuela["NOMBRECT"]),
                    "municipio": limpiar(escuela["NOMBREMUN"]),
                    "localidad": limpiar(escuela["NOMBRELOC"]),
                    "status": limpiar(escuela["STATUS"]),
                    "nivel": limpiar(escuela["NIVEL"]),
                    "subnivel": limpiar(escuela["SUBNIVEL"]),
                    "homo": limpiar(escuela["HOMO"]),
                    "origen": limpiar(escuela["ORIGEN"]),
                    "administrativo": es_administrativa(escuela),
                    "similitud": round(similitud, 2),
                    "puntaje_predictivo": round(puntuacion, 2),
                    "nivel_coincide": nivel_coincide
                })
            opciones_nivel = [opcion for opcion in opciones if opcion["nivel_coincide"] and not opcion["administrativo"]]
            if nivel_cfe is not None and opciones_nivel:
                opciones = opciones_nivel
            opciones.sort(
                key=lambda opcion: (
                    not opcion["administrativo"],
                    opcion["puntaje_predictivo"],
                    opcion["similitud"],
                    esta_activa(opcion["status"])
                ),
                reverse=True
            )
            resultados.append({
                "rpu": limpiar(medidor["RPU"]),
                "nombre_cfe": limpiar(medidor["NOMBRE"]),
                "direccion_cfe": limpiar(medidor["DIRECCION"]),
                "poblacion_cfe": limpiar(medidor["POBLACION"]),
                "tarifa_cfe": limpiar(medidor["TARIFA"]),
                "opciones": opciones[:3]
            })
        return {
            "ok": True,
            "resumen": {
                "registros_seg": len(seg),
                "registros_catalogo_seg": len(catalogo_seg),
                "registros_oficializacion": len(oficializacion),
                "rpu_unicos": len(cfe),
                "registros_cfe_a": len(cfe_a),
                "registros_cfe_b": len(cfe_b),
                "rpu_con_sugerencias": sum(bool(registro["opciones"]) for registro in resultados)
            },
            "resultados": resultados
        }

    if len(sys.argv) not in {4, 5}:
        raise ValueError("Se requieren Catalogo SEG, Oficializacion 911 y al menos un reporte CFE.")

    ruta_cfe_b = Path(sys.argv[4]) if len(sys.argv) == 5 else None
    resultado = procesar(Path(sys.argv[1]), Path(sys.argv[2]), Path(sys.argv[3]), ruta_cfe_b)
    print(json.dumps(resultado, ensure_ascii=False))

except Exception as e:
    print(json.dumps({
        "ok": False,
        "error": f"Error interno en el motor de Python: {str(e)}"
    }, ensure_ascii=False))
    sys.exit(1)
