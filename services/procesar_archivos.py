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
        nivel_seg = normalizar(escuela["NIVEL"])
        nivel_coincide = nivel_cfe is not None and nivel_seg == nivel_cfe
        prioridad = (10 if esta_activa(escuela["STATUS"]) else 0) + (25 if nivel_coincide else 0)
        return similitud, similitud + prioridad, nivel_coincide

    def procesar(ruta_seg, ruta_cfe):
        columnas_seg = ["CCT", "NOMBRECT", "NOMBREMUN", "NOMBRELOC", "STATUS", "NIVEL"]
        columnas_cfe = ["RPU", "NOMBRE", "DIRECCION", "POBLACION", "TARIFA"]
        seg = cargar_excel_seg(ruta_seg, columnas_seg)
        cfe = cargar_excel_cfe(ruta_cfe, columnas_cfe)
        cfe["RPU"] = cfe["RPU"].map(limpiar)
        cfe = cfe[cfe["RPU"].notna() & (cfe["RPU"] != "")]
        cfe = cfe.groupby("RPU", as_index=False, sort=True).agg({
            "NOMBRE": "last",
            "DIRECCION": "last",
            "POBLACION": "last",
            "TARIFA": "last"
        })
        indice_localidades = {}
        for _, escuela in seg.iterrows():
            localidad = normalizar(escuela["NOMBRELOC"])
            if localidad:
                indice_localidades.setdefault(localidad, []).append(escuela)
        resultados = []
        for _, medidor in cfe.iterrows():
            opciones = []
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
            opciones.sort(
                key=lambda opcion: (
                    esta_activa(opcion["status"]),
                    opcion["puntaje_predictivo"],
                    opcion["similitud"]
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
                "rpu_unicos": len(cfe),
                "rpu_con_sugerencias": sum(bool(registro["opciones"]) for registro in resultados)
            },
            "resultados": resultados
        }

    if len(sys.argv) != 3:
        raise ValueError("Se requieren las rutas de los archivos SEG y CFE.")

    resultado = procesar(Path(sys.argv[1]), Path(sys.argv[2]))
    print(json.dumps(resultado, ensure_ascii=False))

except Exception as e:
    print(json.dumps({
        "ok": False,
        "error": f"Error interno en el motor de Python: {str(e)}"
    }, ensure_ascii=False))
    sys.exit(1)
