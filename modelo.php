<?php
namespace Admin;

use Reflection;
use ReflectionMethod;

class DB {
  protected $table;
  protected $primaryKey;
  protected $fillable;
  protected $hidden;

  protected $bindings = [
    'select' => [],
    'join'  => [],
    'where'  => [],
    'orderBy'=> [],
    'limit'  => [],
  ];

  public $operators = [
    '=', '<', '>', '<=', '>=', '<>', '!=', '<=>',
    'like', 'like binary', 'not like', 'ilike',
    '&', '|', '^', '<<', '>>', '&~', 'is', 'is not',
    'rlike', 'not rlike', 'regexp', 'not regexp',
    '~', '~*', '!~', '!~*', 'similar to',
    'not similar to', 'not ilike', '~~*', '!~~*',
    'in', 'not in'
  ];

  protected $tokens = [];
  protected $query;
  protected $instance;

  function __construct($table = '')
  {
    $this->table = $table;
  }

  public static function table($table = '') 
  {
    if ( !(is_string($table) && !empty($table)) ) throw new \Exception('Tabla no definida', 1);
    
    return new DB($table);
  }

  public function select(...$columns)
  {
    foreach ( $columns as $column ) $this->addBinding('select', $column);

    return $this;
  }

  public function selectRaw($query = '')
  {
    if ( !is_string($query) ) throw new \Exception("Se esperaba una cadena (string)", 1);

    return $this->addBinding('select', $query);
  }

  public function where(...$conditions)
  { 
    $boolean = $this->findOrFailBoolean($conditions);
    if ( $boolean !== null ) array_pop($conditions);
    $condition = ( !empty($this->bindings['where'])  ) ? ( $boolean === false ? 'OR' : 'AND' ) : '';
    
    $operator = $this->findOrFailOperator($conditions);
    if ( $operator !== null ) unset($conditions[1]);
    $operator = $operator ?: '=';

    // reset $conditions 
    $conditions = array_values($conditions);
    
    if ( count($conditions) == 1 ) {
      $this->bindings['where'] []= $boolean . reset($conditions);
    } else {
      list($key, $value) = $conditions;

      if ( in_array(strtoupper($operator), ['IN', 'NOT IN']) ) {
        $values = $keysvalues = [];

        foreach ( explode(',', $value) as $k => $v) {
          $values[":" . $key . $k] =  $v;
          $keysvalues []= ":" . $key . $k;
        }

        $this->bindings['where'] []= "$condition $key $operator (" . implode(', ', $keysvalues) . ")";
        array_push($this->tokens, $values);
      } else {
        $this->bindings['where'] []= "$condition " . implode(" $operator :", array_fill(0, 2, $key));
        $this->tokens[':' . $key] = $value;
      }
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
    $typeJoin = ( is_string(end($conditions)) && in_array( strtoupper( end($conditions) ), ['RIGHT', 'LEFT', 'INNER']) ) ? array_pop($conditions) : 'INNER';
    
    if ( count( array_filter($conditions, 'is_array') ) > 0 ) {

      if ( count( array_filter($conditions[0], 'is_array') ) > 0 ) $conditions = $conditions[0];

      foreach ( $conditions as $condition ) {
        if ( (count($condition) > 4) === false ) array_push($condition, $typeJoin);
        call_user_func_array([$this, 'join'], $condition);
      }
      
      return $this;
    }

    $typeJoin .= ' JOIN';

    if ( count($conditions, COUNT_RECURSIVE) !== 4 ) throw new \Exception("Longitud no permitida en join", 1);
    
    list($table, $column1, $operator, $column2) = $conditions;

    $this->addBinding('join', "$typeJoin $table ON $column1 $operator $column2");
    
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
    if ( !empty($order) ) $this->bindings['orderBy'] []= implode(' ', $order);

    return $this;
  }

  public function limit( $count = 0 ) {
    if ( !empty($count) ) $this->bindings['limit'] = [$count];

    return $this;
  }

  public function get() {
  }

  public function find($id) {
    // return $id;
  }

  private function addBinding($binding, $data = '') {
    if ( !in_array($binding, array_keys($this->bindings)) ) throw new \Exception("No existe atadura $binding", 1);
    if ( !is_string($data) ) throw new \Exception("No se puede guardar arregos de arrays", 1);
    
    $this->bindings[$binding] []= $data;

    return $this;
  }
  
  private function getSQL() 
  {
    $select  = !empty($this->bindings['select'])  ? 'SELECT ' . implode(', ', $this->bindings['select']) : '*';
    $from    = 'FROM ' . $this->table;
    $joins   = !empty($this->bindings['join'])    ? implode(' ', $this->bindings['join']) : '';
    $where   = !empty($this->bindings['where'])   ? 'WHERE ' . implode(' ', $this->bindings['where']) : '';
    $orderBy = !empty($this->bindings['orderBy']) ? 'ORDER BY ' . implode(', ', $this->bindings['orderBy']) : '';
    $limit   = !empty($this->bindings['limit'])   ? 'LIMIT ' . implode('', $this->bindings['limit']) : '';

    return $this->query = implode(' ', compact('select','from', 'joins', 'where', 'orderBy', 'limit'));
  }

  private function findOrFailBoolean($array)
  {
    $end = array_pop($array);
    return is_bool($end) ? $end : null; 
  }

  private function findOrFailOperator($array)
  {
    $count = count($array, COUNT_RECURSIVE);
    $operator = array_search(strtolower($array[floor($count/2)]), $this->operators);

    return ($operator !== false) ? strtoupper($this->operators[$operator]) : null; 
  }

  private function __toString()
  {
    return $this->getSQL();
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
      ->join([
              ['leads', 'leads.email', '=', 'socios.email', 'LEFT'],
              ['socios', 'socios.email', '=', 'leads.email'], 
              ['registros', 'leads.email', '=', 'registros.email', 'INNER']
            ])
      ->where('email','luigui@dgtlfundraising')
      ->where('idl', 'NOT IN','1,2,3,4', false)
      ->whereRaw('fecha_ins BETWEEN NOW() AND NOW() - INTERVAL 1 DAY', false)
      ->orderBy('email', 'DESC')
      ->limit(1000);

echo $socio;
?>