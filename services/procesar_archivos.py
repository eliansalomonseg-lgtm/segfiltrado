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

    def cargar_excel_seg(ruta, columnas):
        datos = pd.read_excel(ruta, dtype=object)
        datos.columns = [normalizar(columna).replace(" ", "") for columna in datos.columns]
        faltantes = [columna for columna in columnas if columna not in datos.columns]
        if faltantes:
            raise ValueError(f"Faltan columnas SEG: {', '.join(faltantes)}")
        return datos[columnas].copy()

    def cargar_excel_oficializacion(ruta_oficializacion):
        try:
            df_raw_ofic = pd.read_excel(ruta_oficializacion, header=None, dtype=object)
            df_ofic = None
            columnas_ofic_req = ["CV_CCT", "NOMBRECT", "C_NOM_MUN", "C_NOM_LOC", "TIPO"]
            for idx, row in df_raw_ofic.iterrows():
                row_clean = [str(x).strip().upper() for x in row.values]
                if "CV_CCT" in row_clean and "NOMBRECT" in row_clean:
                    df_ofic = pd.read_excel(ruta_oficializacion, header=idx, dtype=object)
                    break
            if df_ofic is None:
                raise ValueError("No se encontró la fila de cabeceras en el archivo de Oficialización 911.")
            df_ofic.columns = [str(c).strip() for c in df_ofic.columns]
            faltantes_ofic = [col for col in columnas_ofic_req if col not in df_ofic.columns]
            if faltantes_ofic:
                raise ValueError(f"Faltan columnas Oficialización 911: {', '.join(faltantes_ofic)}")
        except Exception as e:
            raise ValueError(f"Error al procesar Oficialización: {str(e)}") from e
        datos = df_ofic[columnas_ofic_req].rename(columns={
            "CV_CCT": "CCT",
            "C_NOM_MUN": "NOMBREMUN",
            "C_NOM_LOC": "NOMBRELOC",
            "TIPO": "NIVEL"
        })
        datos["STATUS"] = "ACTIVO"
        return datos[["CCT", "NOMBRECT", "NOMBREMUN", "NOMBRELOC", "STATUS", "NIVEL"]]

    def cargar_excel_cfe(ruta, columnas):
        datos = pd.read_excel(ruta, header=2, dtype=object)
        datos.columns = [normalizar(columna).replace(" ", "") for columna in datos.columns]
        if not all(columna in datos.columns for columna in columnas):
            datos_crudos = pd.read_excel(ruta, header=None, dtype=object)
            fila_cabecera = None
            for indice, fila in datos_crudos.iterrows():
                valores = [normalizar(valor).replace(" ", "") for valor in fila.values]
                if "RPU" in valores and "POBLACION" in valores:
                    fila_cabecera = indice
                    break
            if fila_cabecera is not None:
                datos = pd.read_excel(ruta, header=fila_cabecera, dtype=object)
                datos.columns = [normalizar(columna).replace(" ", "") for columna in datos.columns]
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

    def esta_activa(status):
        return normalizar(limpiar(status)) in {"1", "ACTIVO", "ACTIVA"}

    def puntuar(nombre_cfe, escuela):
        similitud = SequenceMatcher(
            None,
            normalizar(nombre_cfe),
            normalizar(escuela["NOMBRECT"])
        ).ratio() * 100
        nivel_cfe = identificar_nivel(nombre_cfe)
        nivel_seg = normalizar(escuela["NIVEL"]).replace(" ", "")
        nivel_coincide = nivel_cfe is not None and nivel_cfe in nivel_seg
        prioridad = (10 if esta_activa(escuela["STATUS"]) else 0) + (40 if nivel_coincide else 0)
        return similitud, similitud + prioridad, nivel_coincide

    def procesar(ruta_seg, ruta_oficializacion, ruta_cfe_a, ruta_cfe_b):
        columnas_seg = ["CCT", "NOMBRECT", "NOMBREMUN", "NOMBRELOC", "STATUS", "NIVEL"]
        columnas_cfe = ["RPU", "NOMBRE", "DIRECCION", "POBLACION", "TARIFA"]
        catalogo_seg = cargar_excel_seg(ruta_seg, columnas_seg)
        oficializacion = cargar_excel_oficializacion(ruta_oficializacion)
        catalogo_seg["CCT"] = catalogo_seg["CCT"].map(lambda valor: normalizar(valor).replace(" ", ""))
        oficializacion["CCT"] = oficializacion["CCT"].map(lambda valor: normalizar(valor).replace(" ", ""))
        seg = pd.concat([catalogo_seg, oficializacion], ignore_index=True)
        seg = seg[seg["CCT"].notna() & (seg["CCT"] != "")]
        seg = seg.drop_duplicates(subset=["CCT"], keep="first")
        cfe_a = cargar_excel_cfe(ruta_cfe_a, columnas_cfe)
        cfe_b = cargar_excel_cfe(ruta_cfe_b, columnas_cfe)
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
                    "subnivel": limpiar(escuela["NIVEL"]),
                    "similitud": round(similitud, 2),
                    "puntaje_predictivo": round(puntuacion, 2),
                    "nivel_coincide": nivel_coincide
                })
            opciones_nivel = [opcion for opcion in opciones if opcion["nivel_coincide"]]
            if nivel_cfe is not None and opciones_nivel:
                opciones = opciones_nivel
            opciones.sort(
                key=lambda opcion: (
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

    if len(sys.argv) != 5:
        raise ValueError("Se requieren Catálogo SEG, Oficialización 911 y dos periodos CFE.")

    resultado = procesar(Path(sys.argv[1]), Path(sys.argv[2]), Path(sys.argv[3]), Path(sys.argv[4]))
    print(json.dumps(resultado, ensure_ascii=False))

except Exception as e:
    print(json.dumps({
        "ok": False,
        "error": f"Error interno en el motor de Python: {str(e)}"
    }, ensure_ascii=False))
    sys.exit(1)
