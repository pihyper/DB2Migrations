<?php namespace d3vr\DB2Migrations;

use DB;
use Illuminate\Support\Str;

class SqlMigrations
{
    private static $ignore = array('migrations');
    private static $database = "";
    private static $selects = array('column_name as Field', 'column_type as Type', 'is_nullable as Null', 'column_key as Key', 'column_default as Default', 'extra as Extra', 'data_type as Data_Type');
    private static $instance;
    private static $containers = [];
 
    private static function getTables()
    {
        return DB::select('SELECT table_name FROM information_schema.tables WHERE Table_Type="'."BASE TABLE".'" and table_schema="' . self::$database . '"');
    }
 
    private static function getTableDescribes($table)
    {
        return DB::table('information_schema.columns')
                ->where('table_schema', '=', self::$database)
                ->where('table_name', '=', $table)
                ->get(self::$selects);
    }
 
    private static function getForeignTables()
    {
        return DB::table('information_schema.KEY_COLUMN_USAGE')
                ->where('CONSTRAINT_SCHEMA', '=', self::$database)
                ->where('REFERENCED_TABLE_SCHEMA', '=', self::$database)
                ->select('TABLE_NAME')->distinct()
                ->get();
    }
 
    private static function getForeigns($table)
    {
        return DB::table('information_schema.KEY_COLUMN_USAGE')
                ->where('CONSTRAINT_SCHEMA', '=', self::$database)
                ->where('REFERENCED_TABLE_SCHEMA', '=', self::$database)
                ->where('TABLE_NAME', '=', $table)
                ->select('COLUMN_NAME', 'REFERENCED_TABLE_NAME', 'REFERENCED_COLUMN_NAME')
                ->get();
    }
 
    public function ignore($tables)
    {
        self::$ignore = array_merge($tables, self::$ignore);
        return self::$instance;
    }
 
    public function write()
    {
        // Generate a migration file for each table
        foreach(self::$containers as $table => $values){
            $content = 
            "<?php\n".

            "use Illuminate\Support\Facades\Schema;\n".
            "use Illuminate\Database\Schema\Blueprint;\n".
            "use Illuminate\Database\Migrations\Migration;\n".

            "//\n".
            "// NOTE Migration Created: " . date("Y-m-d H:i:s").
            "// --------------------------------------------------\n\n".
             
            "class Create" . str_replace('_', '', Str::title($table)) . "Table extends Migration {\n\n".
             
            "\tpublic function up()\n".
            "\t{\n".
            "{$values['up']}".
            "\t}\n".
            "\n".
            "\tpublic function down()\n".
            "\t{\n".
            "{$values['down']}\n".
            "\t}\n".
            "}";

            $filename = date('Y_m_d_His') . "_create_" . $table . "table.php";
            $path = app()->databasePath().'/migrations/';
            file_put_contents($path.$filename, $content);
        }
    }
 
    public function convert($database)
    {
        $downStack = array();
        self::$instance = new self();
        self::$database = $database;
        $table_headers = array('Field', 'Type', 'Null', 'Key', 'Default', 'Extra');
        $tables = self::getTables();
        

        foreach ($tables as $key => $value) {
            if (in_array($value->table_name, self::$ignore)) {
                continue;
            }
            self::$containers[$value->table_name] = ["up"=>"", "down"=>""];
 
            $downStack[] = $value->table_name;
            self::$containers[$value->table_name]["down"] = "\t\tSchema::drop('{$value->table_name}');";
            self::$containers[$value->table_name]["up"] = "\t\tSchema::create('{$value->table_name}', function($" . "table) {\n";
            $tableDescribes = self::getTableDescribes($value->table_name);
            foreach ($tableDescribes as $values) {
                $method = "";
                $para = strpos($values->Type, '(');
                $type = $para > -1 ? substr($values->Type, 0, $para) : $values->Type;
                $numbers = "";
                $nullable = $values->Null == "NO" ? "" : "->nullable()";
                $default = empty($values->Default) ? "" : "->default(DB::raw(\"{$values->Default}\"))";
                $unsigned = strpos($values->Type, "unsigned") === false ? '' : '->unsigned()';
                $unique = $values->Key == 'UNI' ? "->unique()" : "";
                $choices = '';
                switch ($type) {
                    case 'enum':
                        $method = 'enum';
                        $choices = preg_replace('/enum/', 'array', $values->Type);
                        $choices = ", $choices";
                        break;
                    case 'int' :
                        $method = 'unsignedInteger';
                        break;
                    case 'bigint' :
                        $method = 'bigInteger';
                        break;
                    case 'samllint' :
                        $method = 'smallInteger';
                        break;
                    case 'char' :
                    case 'varchar' :
                        $para = strpos($values->Type, '(');
                        $numbers = ", " . substr($values->Type, $para + 1, -1);
                        $method = 'string';
                        break;
                    case 'float' :
                        $method = 'float';
                        break;
                    case 'decimal' :
                        $para = strpos($values->Type, '(');
                        $numbers = ", " . substr($values->Type, $para + 1, -1);
                        $method = 'decimal';
                        break;
                    case 'tinyint' :
                        if ($values->Type == 'tinyint(1)') {
                            $method = 'boolean';
                        } else {
                            $method = 'tinyInteger';
                        }
                        break;
                    case 'date':
                        $method = 'date';
                        break;
                    case 'timestamp' :
                        $method = 'timestamp';
                        break;
                    case 'datetime' :
                        $method = 'dateTime';
                        break;
                    case 'mediumtext' :
                        $method = 'mediumtext';
                        break;
                    case 'text' :
                        $method = 'text';
                        break;
                }
                if ($values->Key == 'PRI') {
                    $method = 'increments';
                }
                self::$containers[$value->table_name]["up"] .= "\t\t\t$" . "table->{$method}('{$values->Field}'{$choices}{$numbers}){$nullable}{$default}{$unsigned}{$unique};\n";
            }
 
            self::$containers[$value->table_name]["up"] .= "\t\t});\n\n";
        }

 
        // add foreign constraints, if any
        $tableForeigns = self::getForeignTables();
        if (sizeof($tableForeigns) !== 0) {
            foreach ($tableForeigns as $key => $value) {
                self::$containers[$value->table_name]["up"] = "Schema::table('{$value->TABLE_NAME}', function($" . "table) {\n";
                $foreign = self::getForeigns($value->TABLE_NAME);
                foreach ($foreign as $k => $v) {
                    self::$containers[$value->table_name]["up"] .= " $" . "table->foreign('{$v->COLUMN_NAME}')->references('{$v->REFERENCED_COLUMN_NAME}')->on('{$v->REFERENCED_TABLE_NAME}');\n";
                }
                self::$containers[$value->table_name]["up"] .= " });\n\n";
            }
        }
 
        return self::$instance;
    }
}
