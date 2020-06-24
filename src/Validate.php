<?php
namespace lava\validator;

use Closure;

class Validate
{

    protected $_extend;

    /**
     * @var ValidateAttr[] $_rule;
     */
    protected $_rule = [];
    protected $_error;
    protected $_message = [];
    protected $_lastExtend = [];

    function rule($fields)
    {
        $this->_rule = [];
        foreach($fields as $field=>$value){
            if(strpos($field,'|') !== false){
                [$title,$field] = explode('|',$field);
            }else{
                $title = '';
            }
            if(substr($field,-1) == '?'){
                $allow_null = true;
                $field = substr($field,0,strlen($field)-1);
            }else{
                $allow_null = false;
            }
            $this->setRule($field,$title,$allow_null);
            foreach((array)$value as $extendName=>$rule){
                if(is_int($extendName)){
                    $extendName = $rule;
                    $rule = [];
                }
                $this->addRule($field,$extendName,$rule);
            }
        }
        return $this;
    }
    function setMessageTemplate($name,$value = '')
    {
        if(is_array($name)){
            $this->_message = array_merge([],$this->_message,$name);
        }else{
            $this->_message[$name] = $value;
        }
        return $this;
    }
    function setRule($field,$title = '',$allow_null = false)
    {
        $this->_rule[$field] = new ValidateAttr($field,$title,$allow_null);
        return $this;
    }

    function addRule($field,$name,$rule = [])
    {
        if(!isset($this->_rule[$field])){
            $this->setRule($field);
        }
        $this->_rule[$field]->addExtend($name,$rule);
        return $this;
    }

    function getError()
    {
        return $this->_error;
    }


    protected function setError($value,$replace = [])
    {
        if(count($replace) > 0){
            $value  =  str_replace(array_keys($replace),array_values($replace),$value);
        }
        $this->_error = $value;
    }


    function check($data)
    {
        $this->_error = '';
        foreach($this->_rule as $fieldName=>$attr){
            // /** @var ValidateAttr $attr */
            // if(!isset($data[$fieldName])){
            //     // $attr->allow_null
            //     if($attr->allow_null){
            //         continue;
            //     }
            //     $this->setError(':attribute 不能为空',[
            //         ':attribute'=>$attr->getTitle(),
            //     ]);
            //     return false;
            // }
            // $result = $attr->checkItem($this,$data[$fieldName]);
            // if($result !== true){
            //     $error = is_string($result)?$result:$this->_message[$fieldName.'.'.$this->_lastExtend['name']] ?? $this->_message['*'] ??  ':attribute 验证失败';
            //     $this->setError($error,[
            //         ':attribute'=>$attr->getTitle(),
            //     ]);
            //     return false;
            // }
            $result = $this->checkItem(explode('.',$fieldName),$data,$attr);
            if(!$result){
                return $result;
            }
        }
        return true;
    }

    function checkItem($keys,$data,$attr,$prefix = '')
    {
        foreach($keys as $k=>$key){
            if($key == '*'){
                if(!is_array($data)){
                    $error = $this->_message[$attr->field.'.'.$this->_lastExtend['name']] ?? $this->_message['*'] ??  ':attribute 不是一个数组';
                    $this->setError($error,[
                        ':attribute'=>$attr->title?: $prefix,
                    ]);
                    return false;  
                }
                foreach($data as $data_k=>$val){
                    $result = $this->checkItem(array_slice($keys,$k+1),$val,$attr,$prefix."[$data_k]");
                    if(!$result){
                        return $result;
                    }
                }
                return true;
            }elseif(!isset($data[$key])){
                if($attr->allow_null){
                   return true;
                }
                $this->setError(':attribute 不能为空',[
                    ':attribute'=>empty($attr->title)?ltrim($prefix.".$key",'.'):$attr->title,
                ]);
                return false;
            }else{
                $prefix .= $key;
                $data = $data[$key];
            }
        }
        $result = $attr->checkItem($this,$data);
        if($result !== true){
            $error = is_string($result)?$result:$this->_message[$attr->field.'.'.$this->_lastExtend['name']] ?? $this->_message['*'] ??  ':attribute 验证失败';
            $this->setError($error,[
                ':attribute'=>$attr->title?: $prefix,
            ]);
            return false;
        }else{
            return true;
        }
    }


    /**
     *设置验证器
     * @param string $name 验证器名称
     * @param Closure $callable
     * @return $this
     */
    function extend($extendName,$callable)
    {
        $this->_extend[$extendName] = $callable;
        return $this;
    }

    /**
     * @param string $extendName
     * @param mixed $value
     * @param array $rule
     * @return bool|string
     */
    function call($extendName,$value,$rule = [])
    {
        $this->_lastExtend = [
            'name'=>$extendName,
            'rule'=>$rule
        ];
        foreach((array)$rule as $k=>$v){
           $this->_currentTmplate[':'.$k] = $v;
        }
        if(isset($this->_extend[$extendName])){
            $call = $this->_extend[$extendName];
            return call_user_func($call,$value,$rule);
        }
        $method = $extendName."Extend";
        if(method_exists($this,$method)){
            return $this->$method($value,$rule);
        }else{
            return "$extendName 验证器不存在";
        }
    }


    function mapExtend($value,$rule = [])
    {
        if(!is_array($value)){
            return ':attribute 不是一个map';
        }
        foreach($rule as $key){
            if(!isset($value[$key])){
                return "$key 属性不存在";
            }
        }
        return true;
    }

    function arrayExtend($value,$rule = [])
    {
        if(!is_array($value)){
            return ':attribute 不是一个数组';
        }
        return true;
    }

    function intExtend($value, $rule = [])
    {
        if(!is_scalar($value)){
            return ":attribute 不是一个整数";
        }
        if(preg_match('/^[0-9]{1,}$/',$value."")){
            return true;
        }else{
            return ":attribute 不是一个整型";
        }
    }
    function stringExtend($value, $rule = [])
    {
        if(is_string($value)){
            return true;
        }
        return ":attribute 不是一个字符串";
    }

    function minExtend($value,$rules)
    {
        if(is_string($value)){
            if(mb_strlen($value) >= $rules[0]){
                return true;
            }
        }elseif(is_array($value)){
            if(count($value) >= $rules[0]){
                return true;
            }
        }   
        return ":attribute 最低长度需要{$rules[0]}";
    }
    
    function maxExtend($value,$rules)
    {
        if(is_string($value)){
            if(mb_strlen($value) <= $rules[0]){
                return true;
            }
        }elseif(is_array($value)){
            if(count($value) <= $rules[0]){
                return true;
            }
        }   
        return ":attribute 最大长度不能超过{$rules[0]}";
    }


    function regexpExtend($value,$rules)
    {
        if(!is_scalar($value)){
            return ":attribute 不是一个标量类型";
        }
        if(preg_match($rules[0],$value."")){
            return true;
        }
        return false;
    }

    function enumExtend($value,$rules)
    {
        foreach($rules as $rule){
            if($value == $rule){
                return true;
            }
        }

        return ":attribute 不在枚举之内";
    }

    function betweenExtend($value,$rules)
    {
        if(!is_scalar($value)){
            return ":attribute 不是一个标量类型";
        }
        if($value >=$rules[0] && $value <= $rules[1]){
            return true;
        }else{
            return ":attribute 不在{$rules[0]},{$rules[1]} 范围之内";
        }
    }

    function priceExtend($value,$rules)
    {
        if(!is_scalar($value)){
            return ":attribute 不是一个金额类型";
        }
        if(preg_match('/^[0-9]{1,}(\.[0-9]{0,2})?$/',$value,$match)){
            return true;
        }
        return false;
    }

}