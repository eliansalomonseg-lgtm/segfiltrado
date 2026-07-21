import json
import math
import re
import sys
import unicodedata
from pathlib import Path

import mysql.connector
import pandas as pd

TAMANO_LOTE = 20


def limpiar(valor):
    if valor is None or pd.isna(valor):
        return None
    if isinstance(valor, float) and math.isfinite(valor) and valor.is_integer():
        return str(int(valor))
    texto = str(valor).strip()
    return texto or None


def normalizar(valor):
    texto = limpiar(valor) or ""
    texto = unicodedata.normalize("NFKD", texto)
    texto = "".join(letra for letra in texto if not unicodedata.combining(letra))
    return re.sub(r"\s+", " ", re.sub(r"[^A-Z0-9 ]", " ", texto.upper())).strip()


def codigo(valor):
    return normalizar(valor).replace(" ", "")


def columna_db(valor, usados):
    base = re.sub(r"[^A-Z0-9_]", "_", normalizar(valor).replace(" ", "_")) or "COLUMNA"
    base = base[:54]
    nombre = base
    indice = 2
    while nombre in usados:
        sufijo = f"_{indice}"
        nombre = base[:64 - len(sufijo)] + sufijo
        indice += 1
    usados.add(nombre)
    return nombre


def cabeceras_unicas(datos):
    usados = set()
    columnas_logicas = []
    metadatos = []
    for posicion, original in enumerate(datos.columns, start=1):
        columna = columna_db(original, usados)
        columnas_logicas.append(columna)
        metadatos.append((posicion, str(original), columna))
    datos = datos.copy()
    datos.columns = columnas_logicas
    return datos, columnas_logicas, metadatos


def buscar_cabecera_oficializacion(ruta):
    crudos = pd.read_excel(ruta, header=None, dtype=object)
    for indice, fila in crudos.iterrows():
        if "CVCCT" in [codigo(valor) for valor in fila.values]:
            return indice
    raise ValueError("No se encontro la cabecera CV_CCT en Oficializacion.")


def leer_seg(ruta):
    if ruta.suffix.lower() == ".csv":
        try:
            datos = pd.read_csv(ruta, dtype=object, sep=None, engine="python", encoding="utf-8-sig")
        except UnicodeDecodeError:
            datos = pd.read_csv(ruta, dtype=object, sep=None, engine="python", encoding="latin1")
    else:
        datos = pd.read_excel(ruta, dtype=object)
    datos, columnas, metadatos = cabeceras_unicas(datos)
    requeridas = ["CCT", "NOMBRECT", "TIPOCT"]
    faltantes = [campo for campo in requeridas if campo not in datos.columns]
    if faltantes:
        raise ValueError("Faltan columnas en CCT SEG: " + ", ".join(faltantes))
    return datos, columnas, metadatos


def leer_oficializacion(ruta):
    cabecera = buscar_cabecera_oficializacion(ruta)
    datos = pd.read_excel(ruta, header=cabecera, dtype=object)
    datos, columnas, metadatos = cabeceras_unicas(datos)
    requeridas = ["CV_CCT", "NOMBRECT", "C_NOM_MUN", "C_NOM_LOC"]
    faltantes = [campo for campo in requeridas if campo not in datos.columns]
    if faltantes:
        raise ValueError("Faltan columnas en Oficializacion: " + ", ".join(faltantes))
    return datos, columnas, metadatos


def valor(fila, *campos):
    for campo in campos:
        dato = limpiar(fila.get(campo))
        if dato is not None:
            return dato
    return None


def direccion_oficial(fila):
    directa = valor(fila, "DOMICILIO", "DIRECCION", "UBICACION")
    if directa:
        return directa
    partes = [valor(fila, "C_NOM_VIALIDAD"), valor(fila, "N_EXTNUM")]
    return " ".join(parte for parte in partes if parte) or None


def filas_catalogo(datos, cct_columna):
    filas = []
    for _, serie in datos.iterrows():
        fila = {campo: limpiar(dato) for campo, dato in serie.items()}
        fila["_CCT"] = codigo(fila.get(cct_columna))
        filas.append(fila)
    return filas


def clasificacion(seg, oficial):
    if oficial:
        return "ESCUELA BASICA OFICIALIZADA (ACTIVA)"
    tipo = normalizar(valor(seg, "TIPOCT"))
    estado = normalizar(valor(seg, "STA_DES", "STATUS"))
    if tipo != "ESCUELA":
        return "EDIFICIO ADMINISTRATIVO / INMUEBLE SEG"
    if "INACTIVO" in estado or "CLAUSUR" in estado:
        return "ESCUELA INACTIVA / CLAUSURADA"
    return "SERVICIO SIN COINCIDENCIA (Revision Manual / Verificacion en Campo)"


def perfil_maestro(seg_filas, oficial_filas):
    seg_por_cct = {}
    oficial_por_cct = {}
    for fila in seg_filas:
        if fila["_CCT"]:
            seg_por_cct.setdefault(fila["_CCT"], fila)
    for fila in oficial_filas:
        if fila["_CCT"]:
            oficial_por_cct.setdefault(fila["_CCT"], fila)
    perfiles = []
    for cct in sorted(set(seg_por_cct) | set(oficial_por_cct)):
        seg = seg_por_cct.get(cct)
        oficial = oficial_por_cct.get(cct)
        prioritario = oficial or seg
        nombre = valor(oficial or {}, "NOMBRECT") or valor(seg or {}, "NOMBRECT")
        domicilio = direccion_oficial(oficial or {}) or valor(seg or {}, "DOMICILIO", "DIRECCION")
        municipio = valor(oficial or {}, "C_NOM_MUN") or valor(seg or {}, "NOMBREMUN")
        localidad = valor(oficial or {}, "C_NOM_LOC") or valor(seg or {}, "NOMBRELOC")
        origen = "Oficializacion 911 + CCT SEG" if oficial and seg else "Oficializacion 911" if oficial else "CCT SEG"
        perfiles.append((
            cct,
            nombre or cct,
            domicilio,
            municipio,
            localidad,
            valor(oficial or {}, "STATUS") or valor(seg or {}, "STA_DES", "STATUS") or "ACTIVO",
            valor(oficial or {}, "SUBNIVEL") or valor(seg or {}, "SUBNIVEL"),
            valor(oficial or {}, "NIVEL") or valor(seg or {}, "NIVEL"),
            valor(oficial or {}, "HOMO") or valor(seg or {}, "HOMO"),
            valor(oficial or {}, "TURNO") or valor(seg or {}, "TURNO_DES", "TURNO"),
            valor(oficial or {}, "ZONA") or valor(seg or {}, "CCT_ZONA"),
            valor(oficial or {}, "REGION") or valor(seg or {}, "SERREG"),
            valor(seg or {}, "TIPOCT"),
            valor(seg or {}, "LATITUD"),
            valor(seg or {}, "LONGITUD"),
            valor(seg or {}, "NOM_DIR"),
            valor(seg or {}, "APELLIDO1"),
            valor(seg or {}, "APELLIDO2"),
            valor(seg or {}, "TELEFONO1"),
            valor(seg or {}, "CORREOELE"),
            clasificacion(seg, oficial),
            origen,
            json.dumps(seg, ensure_ascii=False) if seg else None,
            json.dumps(oficial, ensure_ascii=False) if oficial else None,
        ))
    return perfiles


def crear_catalogo(cursor, tabla, columnas):
    cursor.execute(f"DROP TABLE IF EXISTS `{tabla}`")
    definiciones = ["`id` BIGINT AUTO_INCREMENT PRIMARY KEY", "`CCT_NORMALIZADO` VARCHAR(50) NULL"]
    definiciones.extend(f"`{columna}` LONGTEXT NULL" for columna in columnas)
    definiciones.append("`DATOS_JSON` LONGTEXT NULL")
    definiciones.append("INDEX `idx_cct_normalizado` (`CCT_NORMALIZADO`)")
    cursor.execute(f"CREATE TABLE `{tabla}` ({', '.join(definiciones)}) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4")


def guardar_catalogo(cursor, tabla, columnas, filas):
    campos = ["CCT_NORMALIZADO"] + columnas + ["DATOS_JSON"]
    marcadores = ", ".join(["%s"] * len(campos))
    consulta = f"INSERT INTO `{tabla}` ({', '.join('`' + campo + '`' for campo in campos)}) VALUES ({marcadores})"
    registros = []
    for fila in filas:
        datos_json = {campo: fila.get(campo) for campo in columnas if fila.get(campo) is not None}
        registros.append(tuple([fila.get("_CCT")] + [fila.get(campo) for campo in columnas] + [json.dumps(datos_json, ensure_ascii=False)]))
    for inicio in range(0, len(registros), TAMANO_LOTE):
        cursor.executemany(consulta, registros[inicio:inicio + TAMANO_LOTE])


def guardar_metadatos(cursor, fuente, metadatos):
    cursor.execute(
        "CREATE TABLE IF NOT EXISTS catalogo_columnas ("
        "fuente VARCHAR(40) NOT NULL, posicion INT NOT NULL, columna_original VARCHAR(255) NOT NULL, "
        "columna_bd VARCHAR(64) NOT NULL, PRIMARY KEY (fuente, posicion), UNIQUE KEY uniq_catalogo_columna (fuente, columna_bd)"
        ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    )
    cursor.execute("DELETE FROM catalogo_columnas WHERE fuente = %s", (fuente,))
    cursor.executemany(
        "INSERT INTO catalogo_columnas (fuente, posicion, columna_original, columna_bd) VALUES (%s, %s, %s, %s)",
        [(fuente, posicion, original, columna) for posicion, original, columna in metadatos]
    )


def preparar_maestro(cursor):
    cursor.execute(
        "CREATE TABLE IF NOT EXISTS escuelas ("
        "CCT VARCHAR(50) NOT NULL PRIMARY KEY, NOMBRECT VARCHAR(255) NOT NULL, DOMICILIO TEXT NULL, "
        "NOMBREMUN VARCHAR(255) NULL, NOMBRELOC VARCHAR(255) NULL, STATUS VARCHAR(100) NULL, "
        "SUBNIVEL VARCHAR(150) NULL, NIVEL VARCHAR(150) NULL, HOMO VARCHAR(50) NULL, TURNO VARCHAR(150) NULL, "
        "ZONA VARCHAR(100) NULL, REGION VARCHAR(150) NULL, TIPOCT VARCHAR(255) NULL, LATITUD VARCHAR(80) NULL, "
        "LONGITUD VARCHAR(80) NULL, NOM_DIR VARCHAR(255) NULL, APELLIDO1 VARCHAR(255) NULL, APELLIDO2 VARCHAR(255) NULL, "
        "TELEFONO1 VARCHAR(100) NULL, CORREOELE VARCHAR(255) NULL, CLASIFICACION VARCHAR(120) NOT NULL, "
        "ORIGEN VARCHAR(100) NOT NULL, DATOS_SEG_JSON LONGTEXT NULL, DATOS_OFICIALIZACION_JSON LONGTEXT NULL, "
        "INDEX idx_escuelas_clasificacion (CLASIFICACION), INDEX idx_escuelas_municipio (NOMBREMUN), INDEX idx_escuelas_localidad (NOMBRELOC)"
        ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    )
    columnas = {fila[0].upper() for fila in cursor.execute("SHOW COLUMNS FROM escuelas") or cursor.fetchall()}
    adicionales = {
        "CLASIFICACION": "VARCHAR(120) NOT NULL DEFAULT 'SERVICIO SIN COINCIDENCIA (Revision Manual / Verificacion en Campo)'",
        "REGION": "VARCHAR(150) NULL",
        "DATOS_SEG_JSON": "LONGTEXT NULL",
        "DATOS_OFICIALIZACION_JSON": "LONGTEXT NULL",
    }
    for columna, definicion in adicionales.items():
        if columna not in columnas:
            cursor.execute(f"ALTER TABLE escuelas ADD COLUMN `{columna}` {definicion}")


def guardar_maestro(cursor, perfiles):
    cursor.execute("CREATE TABLE IF NOT EXISTS escuelas_rpu_respaldo_catalogo LIKE escuelas_rpu")
    cursor.execute("DELETE FROM escuelas_rpu_respaldo_catalogo")
    cursor.execute("INSERT INTO escuelas_rpu_respaldo_catalogo SELECT * FROM escuelas_rpu")
    cursor.execute("DELETE FROM escuelas")
    consulta = (
        "INSERT INTO escuelas (CCT, NOMBRECT, DOMICILIO, NOMBREMUN, NOMBRELOC, STATUS, SUBNIVEL, NIVEL, HOMO, TURNO, ZONA, REGION, TIPOCT, LATITUD, LONGITUD, NOM_DIR, APELLIDO1, APELLIDO2, TELEFONO1, CORREOELE, CLASIFICACION, ORIGEN, DATOS_SEG_JSON, DATOS_OFICIALIZACION_JSON) "
        "VALUES (" + ", ".join(["%s"] * 24) + ")"
    )
    for inicio in range(0, len(perfiles), TAMANO_LOTE):
        cursor.executemany(consulta, perfiles[inicio:inicio + TAMANO_LOTE])
    cursor.execute(
        "INSERT IGNORE INTO escuelas_rpu (CCT, RPU, nombre_recibo_cfe, poblacion_cfe, tarifa_cfe) "
        "SELECT respaldo.CCT, respaldo.RPU, respaldo.nombre_recibo_cfe, respaldo.poblacion_cfe, respaldo.tarifa_cfe "
        "FROM escuelas_rpu_respaldo_catalogo respaldo INNER JOIN escuelas e ON e.CCT = respaldo.CCT"
    )
    cursor.execute(
        "UPDATE cfe_consumos cc INNER JOIN ("
        "SELECT RPU, MIN(CCT) AS CCT FROM escuelas_rpu GROUP BY RPU HAVING COUNT(DISTINCT CCT) = 1"
        ") vinculo ON vinculo.RPU = cc.RPU SET cc.CCT = vinculo.CCT WHERE cc.CCT IS NULL"
    )


def sincronizar(ruta_seg, ruta_oficializacion):
    datos_seg, columnas_seg, metadatos_seg = leer_seg(ruta_seg)
    datos_oficializacion, columnas_oficializacion, metadatos_oficializacion = leer_oficializacion(ruta_oficializacion)
    filas_seg = filas_catalogo(datos_seg, "CCT")
    filas_oficializacion = filas_catalogo(datos_oficializacion, "CV_CCT")
    perfiles = perfil_maestro(filas_seg, filas_oficializacion)
    conexion = mysql.connector.connect(host="localhost", user="root", password="", database="seg")
    try:
        cursor = conexion.cursor()
        preparar_maestro(cursor)
        crear_catalogo(cursor, "catalogo_seg", columnas_seg)
        crear_catalogo(cursor, "catalogo_oficializacion", columnas_oficializacion)
        guardar_catalogo(cursor, "catalogo_seg", columnas_seg, filas_seg)
        guardar_catalogo(cursor, "catalogo_oficializacion", columnas_oficializacion, filas_oficializacion)
        guardar_metadatos(cursor, "CCT SEG", metadatos_seg)
        guardar_metadatos(cursor, "OFICIALIZACION 911", metadatos_oficializacion)
        guardar_maestro(cursor, perfiles)
        conexion.commit()
        cursor.close()
    except Exception:
        conexion.rollback()
        raise
    finally:
        conexion.close()
    conteo = {
        "ESCUELA BASICA OFICIALIZADA (ACTIVA)": 0,
        "EDIFICIO ADMINISTRATIVO / INMUEBLE SEG": 0,
        "ESCUELA INACTIVA / CLAUSURADA": 0,
        "SERVICIO SIN COINCIDENCIA (Revision Manual / Verificacion en Campo)": 0,
    }
    for perfil in perfiles:
        conteo[perfil[20]] = conteo.get(perfil[20], 0) + 1
    return {"ok": True, "total": len(perfiles), "seg": len(filas_seg), "oficializacion": len(filas_oficializacion), "clasificacion": conteo}


try:
    if len(sys.argv) != 3:
        raise ValueError("Se requieren los catalogos CCT SEG y Oficializacion 911.")
    resultado = sincronizar(Path(sys.argv[1]), Path(sys.argv[2]))
    print(json.dumps(resultado, ensure_ascii=False))
except Exception as error:
    print(json.dumps({"ok": False, "error": f"Fallo al guardar escuelas en base local: {error}"}, ensure_ascii=False))
    sys.exit(1)
