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


def cargar_excel(ruta, columnas, saltar_filas=0):
    datos = pd.read_excel(ruta, skiprows=saltar_filas, dtype=object)
    datos.columns = [normalizar(columna).replace(" ", "") for columna in datos.columns]
    faltantes = [columna for columna in columnas if columna not in datos.columns]
    if faltantes:
        raise ValueError(f"Faltan columnas en {ruta.name}: {', '.join(faltantes)}")
    return datos[columnas].copy()


def nivel_referencia(nombre):
    texto = normalizar(nombre)
    if any(termino in texto for termino in ["KINDER", "JARDIN DE NINOS", "PREESCOLAR"]):
        return ["KINDER", "PREESC", "JARDIN"]
    if "PRIM" in texto:
        return ["PRIM"]
    if "SECUND" in texto or "TELESEC" in texto:
        return ["SECUND", "TELESEC"]
    return []


def esta_activa(status):
    return normalizar(limpiar(status)) in {"1", "ACTIVO", "ACTIVA"}


def puntuar(nombre_cfe, escuela):
    similitud = SequenceMatcher(
        None,
        normalizar(nombre_cfe),
        normalizar(escuela["NOMBRECT"])
    ).ratio() * 100
    terminos = nivel_referencia(nombre_cfe)
    subnivel = normalizar(escuela["SUBNIVEL"])
    nivel_coincide = bool(terminos) and any(termino in subnivel for termino in terminos)
    prioridad = (10 if esta_activa(escuela["STATUS"]) else 0) + (15 if nivel_coincide else 0)
    return similitud, similitud + prioridad, nivel_coincide


def procesar(ruta_seg, ruta_cfe):
    columnas_seg = ["CCT", "NOMBRECT", "NOMBREMUN", "NOMBRELOC", "STATUS", "SUBNIVEL"]
    columnas_cfe = ["RPU", "NOMBRE", "DIRECCION", "POBLACION", "TARIFA"]
    seg = cargar_excel(ruta_seg, columnas_seg)
    cfe = cargar_excel(ruta_cfe, columnas_cfe, 2)
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
                "subnivel": limpiar(escuela["SUBNIVEL"]),
                "similitud": round(similitud, 2),
                "puntaje_predictivo": round(puntuacion, 2),
                "nivel_coincide": nivel_coincide
            })
        opciones.sort(
            key=lambda opcion: (
                esta_activa(opcion["status"]),
                opcion["nivel_coincide"],
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


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("archivo_seg", type=Path)
    parser.add_argument("archivo_cfe", type=Path)
    argumentos = parser.parse_args()
    try:
        print(json.dumps(procesar(argumentos.archivo_seg, argumentos.archivo_cfe), ensure_ascii=False))
    except Exception as error:
        print(json.dumps({"ok": False, "error": str(error)}, ensure_ascii=False))
        sys.exit(1)


if __name__ == "__main__":
    main()
