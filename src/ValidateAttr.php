<?php

namespace lava\validator;

/**
 * @property-read string $field
 * @property-read bool $allow_null
 * 
 */
class ValidateAttr
{


   protected $_extend = [];
   public $_allow_null = false;
   public $_field = '';
   public $_title = '';

   function __construct($field,$title = '',$allow_null = false)
   {
      $this->_field = $field;
      $this->_allow_null = $allow_null;
      $this->_title = $title;
   }

   function addExtend($name,$rule = [])
   {
      $this->_extend[$name] = $rule;
   }
   function clearExtend()
   {
      $this->_extend = [];
   }
   function getExtendTmplate($name)
   {
      foreach($this->_extend[$name]??[] as $key=>$v){
         $data[':'.$key] = $v;
      }
   }

   function getExtends()
   {
      return $this->_extend;
   }

    /**
     *
     * @param Validate $validate
     * @param string $value
     * @return void
     */
    function checkItem($validate,$value)
    {
        foreach($this->getExtends() as $extendName=>$rule){
            $result = $validate->call($extendName,$value,$rule);
            if($result !== true){
                return $result;
            }
        }
        return true;
    }

   function getTitle()
   {
      if(!$this->_title){
         return $this->_field;
      }
      return $this->_title;
   }


   function __get($name)
   {
      if(false !== array_search($name,['field','title','allow_null'])){
         $name = '_'.$name;
         return $this->$name;
      }
      return null;
   }
   function __set($name,$value)
   {
      if(false !== array_search($name,['field','title'])){
         $name = '_'.$name;
         $this->$name = $value;
      }
      return null;
   }
}