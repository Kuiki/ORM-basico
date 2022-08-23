<?php
namespace Admin;

use Reflection;
use ReflectionMethod;

class DB {
  protected $select, $joins, $where, $orderBy, $limit;
  
  protected $table;
  protected $primaryKey;
  protected $fillable;
  protected $hidden;

  protected $query;

  protected $tokens = [];
  protected $instance;

  function __construct($table = '')
  {
    // Para establecer la conexión
    $this->table = $table;
    $this->select = $this->joins = $this->where = $this->orderBy = $this->limit = [];
  }

  public static function table($table = '') 
  {
    if ( !(is_string($table) && !empty($table)) ) throw new \Exception('Tabla no definida', 1);
    
    $instance = new DB($table);

    return $instance;
  }

  public function select(...$columns)
  {
    foreach ( $columns as $column ) $this->select []= $column;
    
    return $this;
  }

  public function selectRaw($query = '')
  {
    $this->select []= $query;

    return $this;
  }

  public function where(...$conditions)
  { 
    $boolean = is_bool(end($conditions)) ? array_pop($conditions) : true;
    $boolean = empty($this->where) ? '' : ( $boolean ? 'AND ' : 'OR ');

    if ( in_array(count($conditions, COUNT_RECURSIVE), [1,2]) ) {
      
      if ( count($conditions) == 1 ) {
        $this->where []= $boolean . $conditions[0]; 
      } else {
        list($key, $value) = $conditions;

        $this->where []= $boolean . implode(" = :", array_fill(0, 2, $key));
        $this->tokens[':' . $key] = $value;
      }
    
    } else if ( count($conditions, COUNT_RECURSIVE) == 3 ) {

      list($key, $condition, $value) = $conditions;

      if ( !in_array(strtoupper($condition), ['IN', 'NOT IN']) ) {
        $this->where []= $boolean . implode( " $condition :", array_fill(0, 2, $key));
        $this->tokens[':' . $key] = $value;
      } else {
        $values = [];
        $keysvalues = [];

        foreach ( explode(',', $value) as $k => $v) {
          $values[":" . $key . $k] =  $v;
          $keysvalues []= ":" . $key . $k;
        }

        $this->where []= $boolean . $key . " $condition (" . implode(', ', $keysvalues) . ")";
        array_push($this->tokens, $values);
      }
    } else {
      throw new \Exception("Número de valores no permitido", 1);
    }

    return $this;
  }

  public function whereRaw($query = '', $boolean = true)
  {
    if ( empty($query) || !filter_var($query, FILTER_SANITIZE_STRING) ) throw new \Exception("Query Inválida", 1);

    call_user_func_array([$this, 'where'], [$query, $boolean]);

    return $this;
  }

  public function join(...$conditions)
  {
    $typeJoin = ( count($conditions, COUNT_RECURSIVE) > 4 ) ? array_pop($conditions) . ' JOIN' : 'INNER JOIN';

    if ( count($conditions, COUNT_RECURSIVE) !== 4 ) throw new \Exception("Longitud no permitida en join", 1);
    
    list($table, $column1, $operator, $column2) = $conditions;

    $this->joins []= "$typeJoin $table ON $column1 $operator $column2";
    
    return $this;
  }

  public function rightJoin(...$conditions)
  {
    array_push($conditions, 'RIGHT');

    call_user_func_array([$this, 'join'], $conditions);

    return $this;
  }

  public function leftJoin(...$conditions)
  {
    array_push($conditions, 'LEFT');

    call_user_func_array([$this, 'join'], $conditions);

    return $this;
  }

  public function raw($sql)
  {
    $this->query = $sql;

    return $this;
  } 

  public function orderBy(...$order) {
    if ( !empty($order) ) $this->orderBy []= implode(' ', $order);

    return $this;
  }

  public function limit( $count = 0 ) {
    if ( !empty($count) ) $this->limit = [$count];

    return $this;
  }

  public function get() {
    // $this->db( $this->convertToSql(), $this->tokens );

  }

  public function find($id) {
    // return $id;
  }
  
  private function convertToSql() 
  {
    $select = 'SELECT ' . implode(', ', $this->select);
    $from  = 'FROM ' . $this->table;
    $joins = implode(' ', $this->joins);
    $where = 'WHERE ' . implode(' ', $this->where);
    $orderBy = !empty($this->orderBy) ? 'ORDER BY ' . implode(', ', $this->orderBy) : '';
    $limit = !empty($this->limit) ? 'LIMIT ' . implode(' ', $this->limit) : '';

    $this->query = implode(' ', compact('select','from', 'joins', 'where', 'orderBy', 'limit'));
  }

  private function __toString()
  {
    $this->convertToSql();
    
    return $this->query;
  }

  private function __call($method, $arguments)
  {
    if ( !method_exists(__CLASS__, $method) )
      throw new \Exception('No existe el método ' . $method .  '() para la tabla ' . $this->table, 1);
  
    if ( (new ReflectionMethod($this, $method))->isPrivate() ) 
      throw new \Exception('Método no permitido', 1);

    elseif ( empty($this->table) ) 
      throw new \Exception('Tabla no definida', 1);

    call_user_func_array([$this, $method], $arguments);
  }
}

$socio = DB::table('socios');

$socio->select('nombre', 'apellidos', 'telefono')
->leftJoin('leads', 'leads.email', '=', 'socios.email')
->rightJoin('registros', 'leads.email', '=', 'registros.email')
->where('email','luigui@dgtlfundraising')
->where('idl', 'NOT IN','1,2,3,4')
->whereRaw('fecha_ins BETWEEN NOW() AND NOW() - INTERVAL 1 DAY', false)
->orderBy('email', 'DESC')
->limit(1000);

echo $socio;
?>