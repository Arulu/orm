===============================================
StupidORM Manual de uso
===============================================
:Autor: Noel García.

.. contents::

******************
¿Qué es StupidORM?
******************

StupidORM es una clase de abstracción de base de datos extremadamente sencilla. Su único fin es evitarnos escribir sentencias SQL directamente, de forma que el acceso a base de datos sea lo más homogéneo posible y el motor de base de datos una pieza más de la aplicación, intercambiable como cualquier otra.

La particularidad de StupidORM es que, a diferencia de otras librerías ORM donde cada entidad de la base de datos se mapea contra una clase, StupidORM utiliza sólo una clase para manejar todas las tablas (StupidORM_Entry), valiéndose de los magic methods __get y __set para mapear los distintos campos de cada tabla.

************
Forma de uso
************

La librería StupidORM se compone a su vez de tres clases:

- StupidORM: básicamente se encarga de la conexión a bbdd y de construir/ejecutar las consultas.
- StupidORM_Entry: representa un registro en una tabla de la base de datos.
- StupidORM_Exception: una simple extensión para Excepciones.

Simple fetch
============
Imaginemos que queremos obtener los registros de una tabla *stupidorm_nombres* compuesta por dos campos: **ID** y *Nombre*.

1. $orm=new StupidORM('mysql:host=MYHOST;dbname=DBNAME', USER, PASSWORD)
2. $rows=$orm->init('stupidorm_nombres','ID')->fetchAll()

Al final de este ejemplo $rows contendría un array de objetos StupidORM_Entry donde cada uno se correspondería con un registro de la tabla stupidorm_nombres. Expliquemos el ejemplo paso a paso:

Al instanciar StupidORM pasamos pasamos una connection string en formato PDO y las credenciales de la BBDD.

Con el método init indicamos la tabla a la que queremos referir la consulta y la primary_key. 

Por defecto StupidORM usa como primary_key el campo ID de la BBDD, por lo que para nuestro ejemplo no sería necesario ese parámetro en la llamada a init.

Init también abre la conexión a la BBDD.

La mayoría de métodos de StupidORM devuelven la propia instancia, por lo que podemos encadenar llamadas a métodos fácilmente.

fetchAll es uno de los "disparadores" de la clase, ésto son métodos que construyen la query con los parámetros suministrados hasta el momento, la ejecutan y devuelven resultados.

fetchAll concretamente, devuelve colecciones de StupidORM_Entry

También tenemos fetchOne y fetchOneForce que devuelven una única instancia StupidORM_Entry y count, que cuenta los registros y devuelve un entero.

En este caso la query ejecutada sería "SELECT * FROM stupidorm_nombres". Podemos obtenerla llamando a getLastQuery.

Creación de un registro
=======================
1. $newReg=$orm->init('stupidorm_nombres')->create()
2. $newReg->Nombre='Pepe'
3. $newReg->save()

Detalle del proceso:

Con init indicamos nuevamente la tabla a tratar. Como la primary_key de la tabla es ID, no es necesario especificarla.

Create crea una nueva instancia de StupidORM_Entry, sin datos y con la flag de $new_entry a TRUE. Podriamos pasar un array de datos con los que se hidrataría la nueva entry.

Como StupidORM_Entry implementa los métodos __get y __set simplemente asignamos valor a los campos como si se tratase de propiedades públicas. También podemos usar $newReg->set('Nombre','Pepe').

Al asignar nuevos valores a un campo, éste se marca como *dirty*. Esto es así para sólo actualizar en la BBDD los campos modificados.

El método save construye una sentencia *insert* debido a que la flag $new_entry está a TRUE.

Nota: si la flag $force se establece a true, en lugar de una sentencia INSERT se construirá una sentencia REPLACE.

Modificación/borrado de un registro
===================================
1. $newReg=$orm->init('stupidorm_nombres')->fetchOne(3)
2. $newReg->Nombre='Pepe'
3. $newReg->save()

Detalle del proceso:

Con init indicamos nuevamente la tabla a tratar. Como la primary_key de la tabla es ID, no es necesario especificarla.

fetchOne admite como parámetro un valor para la primary_key para hacer la búsqueda en la BBDD, y devuelve una instancia de StupidORM_Entry si encuentra coincidencia o false de lo contrario. En este caso la query ejecutada sería *SELECT * FROM stupidorm_nombres WHERE ID=3 LIMIT 1*.

Esta vez la flag $new_entry se establece a FALSE.

La diferencia de este método con respecto a fetchOneForce es que fetchOneForce devuelve una entry vacía si no encuentra resultados.

Como StupidORM_Entry implementa los métodos __get y __set simplemente asignamos valor a los campos como si se tratase de propiedades públicas. También podemos usar $newReg->set('Nombre','Pepe').

Al asignar nuevos valores a un campo, éste se marca como *dirty*. Esto es así para sólo actualizar en la BBDD los campos modificados.

El método save construye una sentencia *update* debido a que la flag $new_entry está a FALSE.

Si en lugar de save llamásemos a delete() borraríamos la entry de la BBDD.

*******************************************
Referencia de métodos (function reference).
*******************************************

StupidORM
=========

Métodos "sql friendly"
**********************
StupidORM cuenta con un gran número de métodos con nombre bastante explícito que no detallaremos en esta documentación, puesto que simplemente añaden a la query final el fragmento sql que define el propio nombre del método. Ejemplo de ello son los métodos **select, join, where, orderByAsc, limit, offset, where_equal**, etc.

Sólo hacer mención que los métodos que en su nombre añaden la partícula raw (**rawQuery, whereRaw**) simplemente concatenan a la query final los parámetros pasados, sin hacer un scape previo de ellos.

Runners
*******
Los métodos **fetchAll, fetchOne, fetchOneForce y count** son los métodos que inician la construcción de la sentencia sql y devuelven resultados, es decir, son los últimos a ser invocados.

init
****
**Init** indica la tabla con la que se trabajará (FROM), y también inicializa la conexión a la BBDD

inTable
*******
**inTable** crea y devuelve un clon de la instancia actual y llama a init para esta nueva instancia.

tableAlias
**********
**tableAlias** establece un alias para la tabla sobre la que se trabaja.

setPrimaryKey
*************
**setPrimaryKey** establece la columna a usar como primary_key.

setConfig
*********
**setConfig** permite cambiar los parámetros de configuración iniciales. Los valores admitidos son:

- connection_string
- id_column
- id_column_overrides
- error_mode
- username
- password
- driver_options
- identifier_quote_character
- logging
- caching

id
**
**id** devuelve la primary_key de un array de datos pasado.

getQueryLog
***********
**getQueryLog** devuelve el log de queries ejecutadas.

getLastQuery
************
**getLastQuery** devuelve la última query en SQL.

getIDColumn
***********
**getIDColumn** devuelve el nombre de la primary_key.

getDB
*****
**getDB** devuelve la instancia PDO usada por la clase.

create
******
**create** devuelve una instancia *StupidORM_Entry* vacía.

save
****
**save** se encarga de la persistencia en BBDD de una entry, haciendo un insert o un update dependiendo de cada caso.

delete
******
**delete** se encarga de la eliminación de registros de la BBDD, recibiendo como parámetro una instancia de StupidORM_Entry.

StupidORM_Entry
===============

asArray
*******
devuelve un array en forma *fieldName=>fieldValue*. Es posible pasarle una lista de fieldNames y sólo devolverá éstos.

delete
******
Borra el registro.

forceAllDirty
*************
Fuerza que todos los campos del registro se actualicen.

getData
*******
Obtiene los campos limpios (no modificados desde la creación del objeto).

getDirtyFields
**************
Obtiene los campos sucios o modificados.

getID
*****
Devuelve la primary_key del registro.

isDirty
*******
Comprueba si el campo pasado esta sucio o no.

isNew
*****
¿Es este un registro nuevo o ya está guardado en BBDD?

now
***
Devuelve la fecha actual en formato MYSQL TIMESTAMP.

unsetDirty/resetDirty
*********************
Marca un/todos los campos como limpios.

save
****
Guarda en la BBDD la entrada actual.

set/setField
************
Modifica el valor de un campo y lo marca como sucio. **SetField** sólo modifica el valor del campo.

updateNewStatus
***************
Cambia la flag de *$new_entry* de true a false o viceversa.
