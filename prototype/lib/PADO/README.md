# PADO : PHP Alternative Database Object

* version    1\.0
* author     Alfasado Inc\. &lt;webmaster@alfasado\.jp&gt;
* copyright  2017 Alfasado Inc\. All Rights Reserved\.

## System Requirements

* PHP version requirements to PHP 5\.6 or 7 or later\.
* Supports UTF\-8 encoded text only\.
* MySQL version 5\.6 or later\.

## Synopsis

### pado.php

    <?php
        require_once( 'class.PADO.php' );
        $db = new PADO();
        $db->configure_from_json( 'db-config.json.cgi' );
        $objects = $db->model( 'entry' )->load();
        foreach ( $objects as $obj ) {
            echo $obj->title, '<br />';
        }
        $entry = $db->model( 'entry' )->new();
        $entry->title( 'PHP Alternative Database Object' );
        $entry->date( date( 'YmdHis' ) );
        $entry->save();

## Methods

### $db\->init\( $config \);

Initialize a Database Connection\.

#### parameters

* array $config: Array for set class properties\.

Required property : dsn or driver, dbname, dbhost, dbuser, dbpasswd, dbport and dbcharset\.


### PADO::get\_instance\(\);

Get instance of class PADO\.

#### return

* object $pado : Object class PADO\.

### $db->configure_from_json( $json );

Set properties from JSON\.

#### parameters

* string $json: JSON file path\.

### $db\->model\( $model \);

Initializing the model\. 
When class exists model use it\. or PADO \+ driver name\(e\.g\.PADOMySQL\) use it\.  
Otherwise use PADOBaseModel\.

#### parameters

* string $model : Name of model\.

#### return

* object $class : Class model object\.

### $db\->register_callback\( $model, $kind, $meth, $priority, $obj = null \);

Register plugin callback\.

#### parameters

* string $model    : Name of model.
* string $kind     : Kind of callback (pre\_save, post\_save, pre\_delete, post\_delete, save\_filter, delete\_filter or pre\_load).
* string $meth     : Function or method name.
* int    $priority : Callback priority.
* object $class    : Plugin class object.

### $db\->run_callbacks\( $cb, $model, $obj, $needle = false \);

Run callbacks\.

#### parameters

* array  $cb     : An array of string callback name, string sql and array values.
* string $model  : Name of model.
* object $obj    : Model object.
* bool   $needle : If specified and save\_filter or delete\_filter callbacks returns false, cancel it.

### $db\->load( $model, $terms, $args, $cols, $extra \);

Load object\.

#### parameters

* string $model   : Name of model\.
* mixed  $terms   : Numeric ID or an array should have keys matching column names and the values are the values for that column\.
* array  $args    : Search options\.
* string $cols    : Get columns from records\. Comma\-separated text or '\*'\.
* string $extra   : String to add to the WHERE statement\. Insufficient care is required for injection\.

#### return

* array  $objects : An array of objects or single object\(Specified Numeric ID\)\.

### $db\->quote\( $str \);

Quotes a string for use in a query\.

#### parameters

* string $str : String to quote\.

#### return

* string $quoted : Quoted string\.

### $db\->stash \($name, $value\);

stash: Where the variable is stored\.

#### parameters

* string $name : Name of set or get variable to\(from\) stash\.
* mixed  $value: Variable for set to stash\.

#### return

* mixed  $var  : Stored data\.

### $db\->escape\_like\($str, $start,$end\);

Quotes a string for like statement\.

#### parameters

* string $str    : String to quote\.
* bool   $start  : Add '%' before $str\.
* bool   $end    : Add '%' after $str\.

#### return

* string $quoted : Quoted string\.

### $db\->clear\_cache\( $model \);

Clear cached objects or valiable\. If model is omitted, all caches are cleared\.

#### parameters

* string $model : Name of model\.

## Properties\(Initial value\)

### $prefix\(''\)

Table prefix\.

### $colprefix\(''\)

Column name prefix\. You can specify wild card strings &lt;table&gt; or &lt;model&gt;\.

### $idxprefix\(''\)

Index name prefix\. You can specify wild card strings &lt;table&gt; or &lt;model&gt;\.

### $id_column\('id'\)

Column name of Primary key\.

### $debug\(false\)

$debug: 1\.error\_reporting\( E\_ALL \) / 2\.debugPrint error\. /3\.debugPrint SQL statement\.

### $upgrader\(false\)

If specified migrate db from $pado\->scheme\[$model\]\.

# PADOBaseModel : PADO Base Model

* version 1\.0

## Synopsis

### model.php

    <?php
        $db->json_model = true;
        $entry = $db->model( 'entry' );
        $terms = ['title' => 'Hello', 'description' => 'This is description of Hello.'];
        $args  = ['limit' => 10, 'offset' => 10, 'sort' => 'id', 'direction' => 'ascend'];

        // Load Objects.
        $entries = $entry->load( $terms, $args );
        foreach ( $entries as $entry ) {
            echo $obj->title, '<br />';
        }

        // Like Statement.
        $phrase = $db->escape_like( 'PADO' );
        $terms['body' => ['like' => $phrase] ];
        $entries = $entry->load( $terms );

        // Count Objects.
        $count = $entry->count( $terms );

        // New Object.
        $entry = $entry->new();
        $entry->title( 'New Entry' );
        $entry->set_values(
            ['body' => 'This is body of new Entry.',
             'date' => date( 'YmdHis' ) ]
        );
        $entry->save();

        // Delete Object.
        $entry = $entry->load( 1 );
        $entry->remove();

### \./models/entry\.json

    {
        "column_defs": {
            "id": {
                "type": "int",
                "size": 11,
                "not_null": 1
            },
            "title": {
                "type": "string",
                "size": 255,
                "not_null": 1
            },
            "body": {
                "type": "text"
            },
            "description" {
                "type": "text"
            },
            "date": {
                "type": "datetime"
            }
        },
        "indexes": {
            "PRIMARY": "id",
            "title": "title",
            "date": "date"
        }
    }

## Methods

### $obj\->new\( $values \);

#### parameters

* array  $params : An array for column names and values for assign\.

#### return

* object $object : New object\.


### $obj\->load\( $terms, $args, $cols, $extra \);

Load object\.

#### parameters

* mixed  $terms   : Numeric ID or an array should have keys matching column names and the values are the values for that column\.
* array  $args    : Search options\.
* string $cols    : Get columns from records\. Comma\-separated text or '\*'\.
* string $extra   : String to add to the WHERE statement\. Insufficient care is required for injection\.

#### return

* array  $objects : An array of objects or single object\(Specified Numeric ID\)\.

### $obj\->get\_by\_key\( $params \);

Load object matches the params\.
If no matching object is found, return new object assigned params\.

#### parameters

* array  $params: An array for search or assign\.

#### return

* object $obj : Single object matches the params or new object assigned params\.

### $obj\->count\( $terms \);

Getting the count of a number of objects\.

#### parameters

* array  $terms : The hash should have keys matching column names and the values are the values for that column\.

#### return

* int $count : Number of objects\.

### $obj\->has\_column\( $name \);

The model has column or not\.

#### parameters

* string $name : Column name\.

#### return

* bool   $has_column : Model has column or not\.

### $obj\->count\_group\_by\( $terms, $args \);

#### parameters

* mixed  $terms  : The hash should have keys matching column names and the values are the values for that column\.
* array  $args   : Columns for grouping\. \(e\.g\.\['group' => \['column1', \.\.\. \] \]\)

#### return

* array $result : An array of conditions and 'COUNT\(\*\)'\.

### $obj\->load_iter( $terms, $args, $cols, $extra \);

#### parameters

* See load method\.

#### return

* object $sth : PDOStatement\.

### $obj\->save();

INSERT or UPDATE the object\.

#### return

* bool $success : Returns true if it succeeds\.

### $obj\->update\(\)

Alias for save\.

### $obj\->remove\(\)

DELETE the object\.

#### return

* bool $success : Returns true if it succeeds\.

### $obj\->delete\(\)

Alias for remove\.

### $obj\->set\_scheme\_from\_json\( $model \)

Get table scheme from JSON file and set to $db->scheme[ $model ]\.

#### parameters

* string $model : Name of model\.

### $obj\->get\_scheme\( $model, $table, $colprefix, $needle \)

Get table scheme from database and set to $db->scheme[ $model ]\.

#### parameters

* string $model     : Name of model\.
* string $table     : Name of table\.
* string $colprefix : Column prefix\.
* bool   $needle    : If specified receive results\(array\)\.

#### return

* array  $scheme    : If $needle specified\.

### $obj\->create_table\( $model, $table, $colprefix, $scheme \)

Create new table from scheme\.

#### parameters

* string $model  : Name of model\.
* string $table  : Name of table\.
* array  $scheme : An array of column definition and index definition\.

### $obj\->column\_values\(\)

Get an array of column names and values\.

#### return

* array $key\-values : Column names and values\.

### $obj\->set\_values\( $params \)

Set column names and values from an array\.

#### parameters

* array $params : The hash for assign\.

### $obj\->get\_values\(\)

Get column names and values except model properties\.

### $obj\->upgrade\( $table, $upgrade, $colprefix \)

Upgrade database scheme\.

#### parameters

* string $table     : Name of table\.
* array  $upgrade   : Scheme information of update columns\.
* string $colprefix : Column name prefix\.

#### return

bool   $success   : Returns true if it succeeds\.

### $obj\->check_upgrade\( $model, $table, $colprefix \)

Compare the schema definition with the actual schema\.

#### parameters

* string $model : Name of model\.
* string $table : Name of table\.
* string $colprefix : Column prefix\.

#### return

* array  $diff  : Difference in array \(\['column\_defs' => $upgrade\_cols, 'indexes' => $upgrade\_idxs \]\)\.

### $obj\->validation\( $values \)

Validate keys and values\.

#### parameters

* array $values : An array for sanitize.

#### return

* array $values : Sanitized an array\.

### $obj\->date2db\( $ts \)

Ymd to Y-m-d

### $obj\->time2db\( $ts \)

His to H:i:s

### $obj\->ts2db\( $ts \)

YmdHis to Y\-m\-d H:i:s

### $obj\->db2ts\( $ts \)

Y\-m\-d H:i:s to YmdHis

# PADOBaseModel : PADO Model for MySQL

* version 1\.0

## Properties\(Initial value\)

PADOMySQL::_engine\('InnoDB'\)

Storage engine\.
