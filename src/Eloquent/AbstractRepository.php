<?php

namespace Oseintow\Eloquent;

use Exception;
use DateTime;
use ReflectionMethod;
use Closure;
use Schema;

abstract class AbstractRepository{

    protected $model;

    protected $request;

    protected $limit = 25;

    protected $page = 0;

    protected $exlcudeFromQuery = [];

    protected $fields = [];

    protected $modelSchema;

    protected $dbSchema;

    public function __construct($model){
        $this->model($model);
        $this->dbSchema = new Schema;
    }

    public function model($model){
        $this->model  = $model;
        $this->modelSchema = $model;
        return $this;
    }

    public function request($request){
        $this->request = $request;
        $this->buildQuery();
        return $this;
    }

    public function buildQuery(){
        if(! isset($this->request)) return $this;
        foreach($this->request->except('all') as $key=>$value){
            if(method_exists($this,camel_case($key))){
                $getfunction = camel_case($key);
                $method = new ReflectionMethod($this, $getfunction);
                $numArgs = $method->getNumberOfRequiredParameters();
                if($numArgs == 1)
                    call_user_func_array([$this,$getfunction], [$value]);
                else
                    call_user_func_array([$this,$getfunction], [$key,$value]);
            }else{
                if(!in_array($key,$this->exlcudeFromQuery)) {
                    $this->getTableColumns();

                    if(in_array($key,$this->fields))
                        $this->where($key, $value);
                    else
                        $this->$key($key,$value);
                }
            }
        }

        return $this;
    }

    public function getTableColumns(){
        if(!count($this->fields) && $this->modelSchema)
            //            $this->fields = $this->modelSchema->getTableColumns();
            $this->fields = Schema::getColumnListing($this->modelSchema->getTable());
    }

    public function __call($key, $parameter){
        if(in_array(substr($key,3),$this->fields))
            $this->model = $this->model->orWhere(substr($parameter[0],3),$parameter[1]);
        //        else if(in_array($key,$this->fields))
        //            $this->model = $this->model->where($parameter[0],$parameter[1]);
        return $this;
    }

    public function with($value){
        $args = func_get_args();
        if(is_array($value)) {
            $this->model = $this->model->with($value);
        }else if(count($args)==1) {
            $explode = explode(",",$value);
            foreach($explode as $exp)
                $this->model = $this->model->with(camel_case($exp));
        }else
            $this->model = $this->model->with($args);
        return $this;
    }

    public function withWhereHas($value){
        $args = explode(",", $value);

        if (count($args) == 4) {
            list($relation1,$relation2, $column, $val) = $args;

            $this->model = $this->model->with([camel_case($relation1)=>function($q) use($relation2,$column, $val){
                $q->whereHas(camel_case($relation2),function($query) use($column, $val){
                    $query->where($column, $val);
                });
            }]);
        }
        else {
            throw new Exception("Where has clause expect three arguments but " . count($args) . " given.", 422);
        }

        return $this;
    }

    public function withWhereHasDate($value, $dateFormat=('Y-m-d')){
        $args = explode(",", $value);

        if (count($args) == 4) {
            list($relation1,$relation2, $column, $val) = $args;
            $val = (new DateTime($val))->format($dateFormat);

            $this->model = $this->model->with([camel_case($relation1)=>function($q) use($relation2,$column, $val){
                $q->whereHas(camel_case($relation2),function($query) use($column, $val){
                    $query->where($column, $val);
                });
            }]);
        }
        else {
            throw new Exception("Where has clause expect four arguments but " . count($args) . " given.", 422);
        }

        return $this;
    }

    public function withWhereHasBetweenDates($value, $dateFormat=('Y-m-d')){
        $args = explode(",", $value);

        if (count($args) == 5) {
            list($relation1,$relation2, $column, $from , $to) = $args;
            $from = (new DateTime($from))->format($dateFormat);
            $to = (new DateTime($to))->format($dateFormat);
            $this->model = $this->model->with([camel_case($relation1)=>function($q) use($relation2,$column, $from, $to){
                $q->whereHas(camel_case($relation2),function($query) use($column, $from, $to){
                    $query->whereBetween($column,[$from,$to]);
                });
            }]);
        }
        else {
            throw new Exception("Where has clause expect five arguments but " . count($args) . " given.", 422);
        }

        return $this;
    }

    public function withIs($value){
        $args = explode(",",$value);
        if(count($args) == 3) {
            list($relation,$column,$val) = $args;
            $this->model = $this->model->with([camel_case($relation) => function($query) use($column,$val){
                $query->where($column,$val);
            }]);
        }else {
            throw new Exception("Where clause expect three arguments but " . count($args) . " given.", 422);
        }

        return $this;
    }

    public function withStartWith($value){
        $args = explode(",",$value);
        if(count($args) == 3) {
            list($relation,$column,$val) = $args;
            $this->model = $this->model->with([camel_case($relation) => function($query) use($column,$val){
                $query->where($column,'like',"$val%");
            }]);
        }else {
            throw new Exception("Where clause expect three arguments but " . count($args) . " given.", 422);
        }

        return $this;
    }

    public function withEndWith($value){
        $args = explode(",",$value);
        if(count($args) == 3) {
            list($relation,$column,$val) = $args;
            $this->model = $this->model->with([camel_case($relation) => function($query) use($column,$val){
                $query->where($column,'like',"%$val");
            }]);
        }else {
            throw new Exception("Where clause expect three arguments but " . count($args) . " given.", 422);
        }

        return $this;
    }

    public function withContains($value){
        $args = explode(",",$value);
        if(count($args) == 3) {
            list($relation,$column,$val) = $args;
            $this->model = $this->model->with([camel_case($relation) => function($query) use($column,$val){
                $query->where($column,'like',"%$val%");
            }]);
        }else {
            throw new Exception("Where clause expect three arguments but " . count($args) . " given.", 422);
        }

        return $this;
    }

    public function page($value){
        $this->page = $value;
    }

    public function limit($value){
        $this->limit = $value;
    }

    public function perPage($value){
        $this->limit = $value;
    }

    public function sinceId($value){
        $this->model = $this->model->where('id',">",$value);
        return $this;
    }

    public function ids($value){
        $this->model = $this->model->whereIn('id',explode(",",$value));
        return $this;
    }

    public function orIds($value){
        $this->model = $this->model->orWhereIn('id',explode(",",$value));
        return $this;
    }

    public function fields($value){
        if($value==null)
            throw new Exception("Fields can not be null",422);

        $this->getTableColumns();

        $values = explode(",",$value);
        foreach($values as $val)
            if(!in_array($val,$this->fields)) throw new Exception("Unknown column: " . $val,422);

        if(!in_array('id',$values)) array_push($values,"id");
        $this->model = $this->model->select($values);

        return $this;
    }

    public function has($args){
        switch (count($args)) {
            case 1:
                $this->model = $this->model->has(camel_case($args));
                break;
            case 2:
                $this->model = $this->model->has(camel_case($args[0]), ">=", $args[2]);
                break;
            case 3:
                $this->model = $this->model->has(camel_case($args[0]), $args[1], $args[2]);
                break;
            default:
                throw new Exception("has clause expect one or three arguments but " . count($args) . " given.",422);
        }

        return $this;
    }

    public function doesntHave($args){
        switch (count($args)) {
            case 1:
                $this->model = $this->model->doesntHave(camel_case($args));
                break;
            case 2:
                $this->model = $this->model->doesntHave(camel_case($args[0]), ">=", $args[2]);
                break;
            case 3:
                $this->model = $this->model->doesntHave(camel_case($args[0]), $args[1], $args[2]);
                break;
            default:
                throw new Exception("DoesntHave clause expect one or three arguments but " . count($args) . " given.",422);
        }

        return $this;
    }

    public function wherDoesntHave($args){
        $this->model = $this->model->whereDoesntHave(camel_case($args));
        return $this;
    }

    public function sum($value){
        try {
            return $this->model->sum($value);
        }catch(Exception $e){
            throw new Exception($e->getMessage(),422);
            throw new Exception("Something unusual happened",422);
        }
    }

    public function get(){
        try {
            if ($this->page >= 1)
                return $this->model->paginate($this->limit)->toArray();
            return $this->model->get();
        }catch(Exception $e){
            throw new Exception($e->getMessage(),422);
            throw new Exception("Something unusual happened",422);
        }
    }

    public function first(){
        try {
            return $this->model->first();
        }catch(Exception $e){
            throw new Exception($e->getMessage(),422);
            throw new Exception("Something unusual happened",422);
        }
    }

    public function selectRaw($value){
        try {
            $this->model = $this->model->selectRaw($value);

            return $this;
        }catch(Exception $e){
            throw new Exception($e->getMessage(),422);
            throw new Exception("Something unusual happened",422);
        }
    }

    public function show($id){
        try {
            return $this->model->find($id);
        }catch(Exception $e){
            throw new Exception($e->getMessage(),422);
            throw new Exception("Something unusual happened",422);
        }
    }

    public function lists($value, $key = null) {
        $lists = $this->model->lists($value, $key);
        if(is_array($lists))
            return $lists;
        return $lists->all();
    }

    public function where(...$args){
        if(count($args)==1)
            if($args[0] instanceof Closure)
                $this->model = $this->model->where($args[0]);
            else
                throw new Exception("Argument must be an instance of Closure",422);
        else if(count($args) == 2)
            $this->model = $this->model->where($args[0],$args[1]);
        else if(count($args) == 3)
            $this->model = $this->model->where($args[0],$args[1],$args[2]);
        else
            throw new Exception("Where clause expect two or three arguments but " . count($args) . " given.",422);

        return $this;
    }

    public function whereDate($value, $dateFormat=('Y-m-d')){
        $args = explode(",", $value);

        if (count($args) == 2) {
            list($column, $val) = $args;
            $val = (new DateTime($val))->format($dateFormat);
            $this->model = $this->model->where($column, $val);
        }
        else {
            throw new Exception("Where has clause expect two arguments but " . count($args) . " given.", 422);
        }

        return $this;
    }

    public function orWhere(...$args){
        if(count($args)==1)
            if($args[0] instanceof Closure)
                $this->model = $this->model->where($args[0]);
            else
                throw new Exception("Argument must be an instance of Closure",422);
        else if(count($args) == 2)
            $this->model = $this->model->orWhere($args[0],$args[1]);
        else if(count($args) == 3)
            $this->model = $this->model->orWhere($args[0],$args[1],$args[2]);
        else
            throw new Exception("Where clause expect two or three arguments but " . count($args) . " given.",422);

        return $this;
    }

    public function startWith($value){
        $args = explode(",",$value);
        if(count($args) == 2)
            $this->model = $this->model->where($args[0],'like',$args[1]."%");
        else
            throw new Exception("Where clause expect two arguments but " . count($args) . " given.",422);

        return $this;
    }

    public function orStartWith($value){
        $args = explode(",",$value);
        if(count($args) == 2)
            $this->model = $this->model->orWhere($args[0],'like',$args[1]."%");
        else
            throw new Exception("Where clause expect two arguments but " . count($args) . " given.",422);

        return $this;
    }

    public function endWith($value){
        $args = explode(",",$value);
        if(count($args) == 2)
            $this->model = $this->model->where($args[0],'like',"%".$args[1]);
        else
            throw new Exception("Where clause expect two arguments but " . count($args) . " given.",422);

        return $this;
    }

    public function orEndWith($value){
        $args = explode(",",$value);
        if(count($args) == 2)
            $this->model = $this->model->orWhere($args[0],'like',"%".$args[1]);
        else
            throw new Exception("Where clause expect two arguments but " . count($args) . " given.",422);

        return $this;
    }

    public function contains($value){
        $args = explode(",",$value);
        if(count($args) == 2)
            $this->model = $this->model->where($args[0],'like',"%$args[1]%");
        else
            throw new Exception("Where clause expect two arguments but " . count($args) . " given.",422);

        return $this;
    }

    public function orContains($value){
        $args = explode(",",$value);
        if(count($args) == 2)
            $this->model = $this->model->orWhere($args[0],'like',"%$args[1]%");
        else
            throw new Exception("Where clause expect two arguments but " . count($args) . " given.",422);

        return $this;
    }

    public function whereHas($value, Closure $closure=null){
        $args = explode(",", $value);

        if ($closure != null) {
            $this->model = $this->model->whereHas($args[0], $closure);
        }
        else if (count($args) == 3) {
            list($relation, $column, $val) = $args;
            $this->model = $this->model->whereHas(camel_case($relation), function ($query) use ($column, $val) {
                $query->where($column, $val);
            });
        }
        else {
            throw new Exception("Where has clause expect three arguments but " . count($args) . " given.", 422);
        }

        return $this;
    }

    public function whereHasDate($value, $dateFormat=('Y-m-d')){
        $args = explode(",", $value);

        if (count($args) == 3) {
            list($relation, $column, $val) = $args;
            $val = (new DateTime($val))->format($dateFormat);
            $this->model = $this->model->whereHas(camel_case($relation), function ($query) use ($column, $val) {
                $query->where($column, $val);
            });
        }
        else {
            throw new Exception("Where has clause expect three arguments but " . count($args) . " given.", 422);
        }

        return $this;
    }

    public function whereHasBetweenDates($value,$dateFormat=('Y-m-d')){
        $args = explode(",", $value);

        if (count($args) == 4) {
            list($relation, $column, $from , $to) = $args;
            $from = (new DateTime($from))->format($dateFormat);
            $to = (new DateTime($to))->format($dateFormat);
            $this->model = $this->model->whereHas(camel_case($relation), function ($query) use ($column, $from, $to) {
                $query->whereBetween($column,[$from,$to]);
            });
        }
        else {
            throw new Exception("Where has clause expect four arguments but " . count($args) . " given.", 422);
        }

        return $this;
    }

    public function whereHasStartWith($value){
        $args = explode(",",$value);
        if(count($args) == 3) {
            list($relation,$column,$val) = $args;
            $this->model =  $this->model->whereHas(camel_case($relation),function($query) use($column,$val){
                $query->where($column,'like',$val ."%");
            });
        }else {
            throw new Exception("Where clause expect three arguments but " . count($args) . " given.", 422);
        }

        return $this;
    }

    public function whereHasEndWith($value){
        $args = explode(",",$value);
        if(count($args) == 3) {
            list($relation,$column,$val) = $args;
            $this->model =  $this->model->whereHas(camel_case($relation),function($query) use($column,$val){
                $query->where($column,'like', "%".$val);
            });
        }else {
            throw new Exception("Where clause expect three arguments but " . count($args) . " given.", 422);
        }

        return $this;
    }

    public function whereHasContains($value){
        $args = explode(",",$value);
        if(count($args) == 3) {
            list($relation,$column,$val) = $args;
            $this->model =  $this->model->whereHas(camel_case($relation),function($query) use($column,$val){
                $query->where($column,'like',"%$val%");
            });
        }else {
            throw new Exception("Where clause expect three arguments but " . count($args) . " given.", 422);
        }

        return $this;
    }

    public function between($value){
        $values = explode(",",$value);
        if(count($values) != 3)
            throw new Exception("Between expect three values but " . count($values) . " given",422);
        return $this->whereBetween($values[0],$values[1],$values[2]);
    }

    public function betweenDates($value,$dateFormat=('Y-m-d')){
        $values = explode(",", $value);

        if (!in_array(count($values),[3,4]))
            throw new Exception("Between dates expect three or four values but " . count($values) . " given", 422);

        try {
            if (isset($values[3])) $dateFormat = $values[3];
            $values[1] = (new DateTime($values[1]))->format($dateFormat);
            $values[2] = (new DateTime($values[2]))->format($dateFormat);

            return $this->whereBetween($values[0], $values[1], $values[2]);
        }catch(Exception $e){
            throw new Exception("Invalid date exception",422);
        }
    }

    public function whereBetween($column, $from, $to){
        $this->model = $this->model->whereBetween($column,[$from,$to]);
        return $this;
    }

    public function sinceDate($value){
        return $this->getBeforeAndSinceDate($value,">=");
    }

    public function beforeDate($value){
        return $this->getBeforeAndSinceDate($value,"<");
    }

    public function getBeforeAndSinceDate($value,$operator,$dateFormat=('Y-m-d') ){
        $values = explode(",", $value);

        if (!in_array(count($values),[2,3]))
            throw new Exception("Since or before date expect two or three values but " . count($values) . " given", 422);

        try {
            if (isset($values[2])) $dateFormat = $values[2];
            $values[1] = (new DateTime($values[1]))->format($dateFormat);

            $this->model = $this->model->where($values[0], $operator ,$values[1]);

            return $this;

        }catch(Exception $e){
            throw new Exception("Invalid date exception",422);
        }
    }

    public function orderByAsc($value){
        $this->model = $this->model->orderBy($value,"ASC");
        return $this;
    }

    public function orderByDesc($value){
        $this->model = $this->model->orderBy($value,"DESC");
        return $this;
    }

    public function deleteBelongsToRecords($relationship, $attribute, $ids){
        $this->model->whereHas($relationship, function ($query) use ($attribute)
        {
            $query->where('id', $attribute->id);
        })->whereNotIn('id', $ids)->delete();
    }

    public function deleteAssociatedRecords($model,$relationship, $attributes, $ids){
        $model->whereHas($relationship, function ($query) use ($attributes)
        {
            $query->where('id', $attributes->id);
        })->whereNotIn('id', $ids)->delete();
    }

}