import sys
import json

try:
    sys.stdout.reconfigure(encoding="utf-8")

    import math
    import re
    import unicodedata
    from pathlib import Path

    import pandas as pd
    import mysql.connector

    TAMANO_LOTE = 500
    QUERY_INSERTAR_ESCUELAS = "INSERT INTO escuelas (CCT, NOMBRECT, NOMBREMUN, NOMBRELOC, STATUS, SUBNIVEL) VALUES (%s, %s, %s, %s, %s, %s) ON DUPLICATE KEY UPDATE NOMBRECT=VALUES(NOMBRECT), NOMBREMUN=VALUES(NOMBREMUN), NOMBRELOC=VALUES(NOMBRELOC), STATUS=VALUES(STATUS), SUBNIVEL=VALUES(SUBNIVEL)"

    def normalizar(valor):
        if valor is None or pd.isna(valor):
            return ""
        texto = unicodedata.normalize("NFKD", str(valor))
        texto = "".join(letra for letra in texto if not unicodedata.combining(letra))
        return re.sub(r"\s+", " ", re.sub(r"[^A-Z0-9 ]", " ", texto.upper())).strip()

    def normalizar_codigo(valor):
        return normalizar(valor).replace(" ", "")

    def normalizar_cabecera(valor):
        texto = str(valor).replace("\ufeff", "").replace("ï»¿", "").replace("Ã¯Â»Â¿", "").strip()
        return normalizar_codigo(texto)

    def limpiar(valor):
        if valor is None or pd.isna(valor):
            return None
        if isinstance(valor, float) and math.isfinite(valor) and valor.is_integer():
            return str(int(valor))
        texto = str(valor).strip()
        return texto if texto != "" else None

    def preparar_columnas(datos):
        datos.columns = [normalizar_cabecera(columna) for columna in datos.columns]
        return datos

    def leer_catalogo_seg(ruta):
        ruta = Path(ruta)
        if ruta.suffix.lower() == ".csv":
            try:
                datos = pd.read_csv(ruta, dtype=object, sep=None, engine="python", encoding="utf-8-sig")
            except UnicodeDecodeError:
                datos = pd.read_csv(ruta, dtype=object, sep=None, engine="python", encoding="latin1")
        else:
            datos = pd.read_excel(ruta, dtype=object)
        datos = preparar_columnas(datos)
        requeridas = ["CCT", "NOMBRECT", "NOMBREMUN", "NOMBRELOC", "STATUS", "SUBNIVEL", "SOSTCONTROL"]
        faltantes = [columna for columna in requeridas if columna not in datos.columns]
        if faltantes:
            raise ValueError(f"Faltan columnas Catalogo SEG: {', '.join(faltantes)}")
        datos = datos[datos["SOSTCONTROL"].map(normalizar) == "PUBLICO"].copy()
        datos["_PRIORIDAD_ORIGEN"] = 0
        return datos[["CCT", "NOMBRECT", "NOMBREMUN", "NOMBRELOC", "STATUS", "SUBNIVEL", "_PRIORIDAD_ORIGEN"]]

    def leer_oficializacion(ruta):
        crudos = pd.read_excel(ruta, header=None, dtype=object)
        datos = None
        for indice, fila in crudos.iterrows():
            valores = [normalizar_codigo(valor) for valor in fila.values]
            if "CVCCT" in valores:
                datos = preparar_columnas(pd.read_excel(ruta, header=indice, dtype=object))
                break
        if datos is None:
            raise ValueError("No se encontro la fila de cabeceras con CV_CCT en Oficializacion 911.")
        requeridas = ["CVCCT", "NOMBRECT", "CNOMMUN", "CNOMLOC", "CONTROL"]
        faltantes = [columna for columna in requeridas if columna not in datos.columns]
        if faltantes:
            raise ValueError(f"Faltan columnas Oficializacion 911: {', '.join(faltantes)}")
        datos = datos[datos["CONTROL"].map(normalizar) == "PUBLICO"].copy()
        salida = pd.DataFrame({
            "CCT": datos["CVCCT"],
            "NOMBRECT": datos["NOMBRECT"],
            "NOMBREMUN": datos["CNOMMUN"],
            "NOMBRELOC": datos["CNOMLOC"],
            "STATUS": datos["STATUS"] if "STATUS" in datos.columns else "ACTIVO",
            "SUBNIVEL": datos["SUBNIVEL"] if "SUBNIVEL" in datos.columns else datos["TIPO"] if "TIPO" in datos.columns else datos["HOMO"] if "HOMO" in datos.columns else None,
            "_PRIORIDAD_ORIGEN": 1,
        })
        return salida

    def preparar_registros(conjuntos):
        datos = pd.concat(conjuntos, ignore_index=True)
        datos["CCT"] = datos["CCT"].map(normalizar_codigo)
        datos = datos[datos["CCT"].notna() & (datos["CCT"] != "")]
        datos = datos.sort_values("_PRIORIDAD_ORIGEN").drop_duplicates(subset=["CCT"], keep="last")
        registros = []
        for _, fila in datos.iterrows():
            registros.append((
                limpiar(fila["CCT"]),
                limpiar(fila["NOMBRECT"]),
                limpiar(fila["NOMBREMUN"]),
                limpiar(fila["NOMBRELOC"]),
                limpiar(fila["STATUS"]),
                limpiar(fila["SUBNIVEL"]),
            ))
        return registros

    def guardar(registros):
        if not registros:
            return 0
        conexion = mysql.connector.connect(host="localhost", user="root", password="", database="seg")
        try:
            cursor = conexion.cursor()
            for inicio in range(0, len(registros), TAMANO_LOTE):
                cursor.executemany(QUERY_INSERTAR_ESCUELAS, registros[inicio:inicio + TAMANO_LOTE])
            conexion.commit()
            cursor.close()
            return len(registros)
        finally:
            conexion.close()

    if len(sys.argv) < 2:
        raise ValueError("Se requiere al menos un catalogo escolar.")

    conjuntos = []
    for argumento in sys.argv[1:]:
        ruta = Path(argumento)
        nombre = ruta.name.lower()
        if "oficial" in nombre or "911" in nombre:
            conjuntos.append(leer_oficializacion(ruta))
        else:
            conjuntos.append(leer_catalogo_seg(ruta))
    registros = preparar_registros(conjuntos)
    total = guardar(registros)
    print(json.dumps({"ok": True, "total": total}, ensure_ascii=False))

except Exception as e:
    print(json.dumps({
        "ok": False,
        "error": f"Fallo al guardar escuelas en base local: {str(e)}"
    }, ensure_ascii=False))
    sys.exit(1)
