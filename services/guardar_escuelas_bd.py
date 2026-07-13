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

    TAMANO_LOTE = 100
    COLUMNAS_BASE = ["CCT", "NOMBRECT", "DOMICILIO", "NOMBREMUN", "NOMBRELOC", "STATUS", "SUBNIVEL", "NIVEL", "HOMO", "TURNO", "ZONA", "SECTOR", "ORIGEN"]
    COLUMNAS_DETALLE = [
        "TIPOCT", "TURNO_CV", "TURNO2", "TURNO2_DES", "STATUS_DES", "MPIO", "LOC", "AMBITO",
        "COLONIA", "NOMBRECOL", "ENTRECALLE", "YCALLE", "CALLEPOST", "CODPOST", "LATITUD", "LONGITUD",
        "CV_INMUEBLE", "MARGINACION", "CCT_ZONA", "CCT_SECTOR", "SERREG", "CCT_SERREG", "TIPO",
        "SERVICIO", "SERVICIO_DES", "CV_CARACT", "CARACTERISTICA", "SOST_CONTROL", "SOSTENIMIENTO",
        "SOSTENIMIENTO_DES", "NOM_DIR", "APELLIDO1", "APELLIDO2", "CURP", "RFC", "TELEFONO1", "CELULAR1",
        "CORREOELE", "PAGINAWEB", "ADM_DES", "NOR_DES", "OPERAT_DES", "FECHAFUNDA", "FECHAALTA",
        "FECHACLAUS", "FECHAREAPE", "FECHAACTUA", "CLAVE_ALTERNA", "CV_TURNO", "CV_MUN", "CV_LOC",
        "C_NOM_VIALIDAD", "N_EXTNUM", "CONTROL", "SUBCONTROL", "C_CARACTERIZAN2", "JEFSEC", "SERVREG",
        "REGION", "CV_ESTATUS_CAPTURA", "HOMBRE", "MUJER", "TOTAL", "GRUPOS", "LENGUA",
        "DATOS_SEG_JSON", "DATOS_OFICIALIZACION_JSON"
    ]
    COLUMNAS_ESCUELAS = COLUMNAS_BASE + COLUMNAS_DETALLE
    QUERY_INSERTAR_ESCUELAS = (
        "INSERT INTO escuelas (" + ", ".join("`" + columna + "`" for columna in COLUMNAS_ESCUELAS) + ") VALUES (" + ", ".join(["%s"] * len(COLUMNAS_ESCUELAS)) + ") "
        "ON DUPLICATE KEY UPDATE " + ", ".join(["`" + columna + "`=VALUES(`" + columna + "`)" for columna in COLUMNAS_ESCUELAS if columna != "CCT"])
    )
    COLUMNAS_DIRECCION = ["DOMICILIO", "DOMICILIOCT", "DOMICILIOCCT", "DOMICILIODELCT", "DIRECCION", "DIRECCIONCT", "UBICACION", "CALLE"]
    COLUMNAS_EXTRA = {
        "NIVEL": "VARCHAR(100) NULL",
        "HOMO": "VARCHAR(30) NULL",
        "TURNO": "VARCHAR(100) NULL",
        "ZONA": "VARCHAR(50) NULL",
        "SECTOR": "VARCHAR(50) NULL",
        "ORIGEN": "VARCHAR(80) NULL",
    }
    for columna in COLUMNAS_DETALLE:
        COLUMNAS_EXTRA[columna] = "LONGTEXT NULL" if columna.endswith("_JSON") else "VARCHAR(255) NULL"

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

    def primer_valor(*valores):
        for valor in valores:
            limpio = limpiar(valor)
            if limpio is not None:
                return limpio
        return None

    def preparar_columnas(datos):
        columnas = []
        conteo = {}
        for columna in datos.columns:
            nombre = normalizar_cabecera(columna)
            conteo[nombre] = conteo.get(nombre, 0) + 1
            columnas.append(nombre if conteo[nombre] == 1 else f"{nombre}_{conteo[nombre]}")
        datos.columns = columnas
        return datos

    def columna_direccion(datos):
        for columna in COLUMNAS_DIRECCION:
            if columna in datos.columns:
                return datos[columna]
        return None

    def serie(datos, columnas, valor=None):
        for columna in columnas:
            if columna in datos.columns:
                return datos[columna]
        return pd.Series([valor] * len(datos), index=datos.index)

    def copiar_columnas(datos, mapa):
        salida = {}
        for destino, origenes in mapa.items():
            salida[destino] = serie(datos, origenes if isinstance(origenes, list) else [origenes])
        return salida

    def json_fila(fila):
        datos = {}
        for columna, valor in fila.items():
            limpio = limpiar(valor)
            if limpio is not None:
                datos[str(columna)] = limpio
        return json.dumps(datos, ensure_ascii=False)

    def direccion_oficializacion(datos):
        directa = columna_direccion(datos)
        if directa is not None:
            return directa
        vialidad = serie(datos, ["CNOMVIALIDAD"], "")
        numero = serie(datos, ["NEXTNUM"], "")
        return (vialidad.fillna("").astype(str).str.strip() + " " + numero.fillna("").astype(str).str.strip()).str.strip()

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
        direccion = columna_direccion(datos)
        datos["DOMICILIO"] = direccion if direccion is not None else None
        datos["NIVEL"] = serie(datos, ["NIVEL"])
        datos["HOMO"] = serie(datos, ["HOMO"])
        datos["ORIGEN"] = "Catalogo SEG"
        datos["TURNO"] = serie(datos, ["TURNODES", "TURNO"])
        datos["ZONA"] = serie(datos, ["CCTZONA", "ZONA"])
        datos["SECTOR"] = serie(datos, ["CCTSECTOR", "SECTOR"])
        extras = copiar_columnas(datos, {
            "TIPOCT": "TIPOCT", "TURNO_CV": "TURNO", "TURNO2": "TURNO2", "TURNO2_DES": "TUR2DES",
            "STATUS_DES": "STADES", "MPIO": "MPIO", "LOC": "LOC", "AMBITO": "AMBITO", "COLONIA": "COLONIA",
            "NOMBRECOL": "NOMBRECOL", "ENTRECALLE": "ENTRECALLE", "YCALLE": "YCALLE", "CALLEPOST": "CALLEPOST",
            "CODPOST": "CODPOST", "LATITUD": "LATITUD", "LONGITUD": "LONGITUD", "CV_INMUEBLE": "CVINMUEBLE",
            "MARGINACION": "MARGINACION", "CCT_ZONA": "CCTZONA", "CCT_SECTOR": "CCTSECTOR", "SERREG": "SERREG",
            "CCT_SERREG": "CCTSERREG", "TIPO": "TIPO", "SERVICIO": "SERVICIO", "SERVICIO_DES": "SERDES",
            "CV_CARACT": "CVCARACT", "CARACTERISTICA": "CARACTERISTICA", "SOST_CONTROL": "SOSTCONTROL",
            "SOSTENIMIENTO": "SOSTENIMIE", "SOSTENIMIENTO_DES": "SOSDES", "NOM_DIR": "NOMDIR",
            "APELLIDO1": "APELLIDO1", "APELLIDO2": "APELLIDO2", "CURP": "CURP", "RFC": "RFC",
            "TELEFONO1": "TELEFONO1", "CELULAR1": "CELULAR1", "CORREOELE": "CORREOELE", "PAGINAWEB": "PAGINAWEB",
            "ADM_DES": ["ADMDES", "ADMDES2"], "NOR_DES": ["NORDES", "NORDES4"], "OPERAT_DES": "OPERATDES",
            "FECHAFUNDA": "FECHAFUNDA", "FECHAALTA": "FECHAALTA", "FECHACLAUS": "FECHACLAUS",
            "FECHAREAPE": "FECHAREAPE", "FECHAACTUA": "FECHAACTUA"
        })
        for columna, valores in extras.items():
            datos[columna] = valores
        datos["DATOS_SEG_JSON"] = datos.apply(json_fila, axis=1)
        datos["DATOS_OFICIALIZACION_JSON"] = None
        datos["_PRIORIDAD_ORIGEN"] = 0
        for columna in COLUMNAS_ESCUELAS:
            if columna not in datos.columns:
                datos[columna] = None
        return datos[COLUMNAS_ESCUELAS + ["_PRIORIDAD_ORIGEN"]]

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
        direccion = direccion_oficializacion(datos)
        salida = pd.DataFrame({
            "CCT": datos["CVCCT"],
            "NOMBRECT": datos["NOMBRECT"],
            "DOMICILIO": direccion if direccion is not None else None,
            "NOMBREMUN": datos["CNOMMUN"],
            "NOMBRELOC": datos["CNOMLOC"],
            "STATUS": datos["STATUS"] if "STATUS" in datos.columns else "ACTIVO",
            "SUBNIVEL": datos["SUBNIVEL"] if "SUBNIVEL" in datos.columns else datos["TIPO"] if "TIPO" in datos.columns else datos["HOMO"] if "HOMO" in datos.columns else None,
            "NIVEL": serie(datos, ["NIVEL"]),
            "HOMO": serie(datos, ["HOMO"]),
            "TURNO": serie(datos, ["TURNO"]),
            "ZONA": serie(datos, ["ZONA"]),
            "SECTOR": serie(datos, ["JEFSEC", "SECTOR"]),
            "ORIGEN": "Oficializacion 911",
            "_PRIORIDAD_ORIGEN": 1,
        })
        extras = copiar_columnas(datos, {
            "CLAVE_ALTERNA": "CLAVEALTERNA", "CV_TURNO": "CVTURNO", "CV_MUN": "CVMUN", "CV_LOC": "CVLOC",
            "C_NOM_VIALIDAD": "CNOMVIALIDAD", "N_EXTNUM": "NEXTNUM", "CONTROL": "CONTROL",
            "SUBCONTROL": "SUBCONTROL", "TIPO": "TIPO", "C_CARACTERIZAN2": "CCARACTERIZAN2", "JEFSEC": "JEFSEC",
            "SERVREG": "SERVREG", "REGION": "REGION", "CV_ESTATUS_CAPTURA": "CVESTATUSCAPTURA",
            "HOMBRE": "HOMBRE", "MUJER": "MUJER", "TOTAL": "TOTAL", "GRUPOS": "GRUPOS", "LENGUA": "LENGUA"
        })
        for columna, valores in extras.items():
            salida[columna] = valores
        salida["DATOS_SEG_JSON"] = None
        salida["DATOS_OFICIALIZACION_JSON"] = datos.apply(json_fila, axis=1)
        for columna in COLUMNAS_ESCUELAS:
            if columna not in salida.columns:
                salida[columna] = None
        return salida

    def preparar_registros(conjuntos):
        datos = pd.concat(conjuntos, ignore_index=True)
        datos["CCT"] = datos["CCT"].map(normalizar_codigo)
        datos = datos[datos["CCT"].notna() & (datos["CCT"] != "")]
        datos = datos.sort_values("_PRIORIDAD_ORIGEN")
        fusionados = []
        for _, grupo in datos.groupby("CCT", sort=False):
            filas = grupo.to_dict("records")
            oficial = next((fila for fila in reversed(filas) if fila.get("ORIGEN") == "Oficializacion 911"), None)
            seg = next((fila for fila in filas if fila.get("ORIGEN") == "Catalogo SEG"), None)
            fila = {}
            for columna in COLUMNAS_ESCUELAS:
                fila[columna] = primer_valor(
                    oficial.get(columna) if oficial else None,
                    seg.get(columna) if seg else None,
                    *(item.get(columna) for item in reversed(filas))
                )
            origenes = []
            if seg:
                origenes.append("Catalogo SEG")
                fila["DATOS_SEG_JSON"] = primer_valor(seg.get("DATOS_SEG_JSON"), fila.get("DATOS_SEG_JSON"))
            if oficial:
                origenes.append("Oficializacion 911")
                fila["DATOS_OFICIALIZACION_JSON"] = primer_valor(oficial.get("DATOS_OFICIALIZACION_JSON"), fila.get("DATOS_OFICIALIZACION_JSON"))
            fila["ORIGEN"] = " + ".join(origenes) if origenes else fila.get("ORIGEN")
            fusionados.append(fila)
        datos = pd.DataFrame(fusionados)
        registros = []
        for _, fila in datos.iterrows():
            registros.append(tuple(limpiar(fila[columna]) for columna in COLUMNAS_ESCUELAS))
        return registros

    def preparar_tabla(cursor):
        cursor.execute("SHOW COLUMNS FROM escuelas")
        existentes = {fila[0].upper() for fila in cursor.fetchall()}
        for columna, definicion in COLUMNAS_EXTRA.items():
            if columna not in existentes:
                cursor.execute(f"ALTER TABLE escuelas ADD COLUMN `{columna}` {definicion}")

    def guardar(registros):
        if not registros:
            return 0
        conexion = mysql.connector.connect(host="localhost", user="root", password="", database="seg")
        try:
            cursor = conexion.cursor()
            preparar_tabla(cursor)
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
