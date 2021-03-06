<?php



 class Forum extends ActiveRecord
 {
     // ActiveRecord configuration
    public static $tableName = 'forums';
     public static $singularNoun = 'forum';
     public static $pluralNoun = 'forums';

    // required for shared-table subclassing support
    public static $rootClass = __CLASS__;
     public static $defaultClass = __CLASS__;
     public static $subClasses = array(__CLASS__);

     public static $fields = array(
        'ContextClass' => null // uncomment to enable
        ,'ContextID' => null
        ,'Handle' => array(
            'unique' => true
        )
        ,'Status' => array(
            'type' => 'enum'
            ,'values' => array('Live','Deleted')
            ,'default' => 'Live'
        )
        ,'Title'
        ,'Description'
        ,'LastPost' => array(
            'type' => 'timestamp'
            ,'notnull' => false
        )
        ,'RequiredAccountLevel' => array(
            'type' => 'enum'
            ,'values' => array()
            ,'notnull' => false
        )
        ,'Position' => array(
            'type' => 'integer'
            ,'unsigned' => false
            ,'default' => 0
        )
    );

     public static $relationships = array(
        'Discussions' => array(
            'type' => 'context-children'
            ,'class' => 'Discussion'
            ,'contextClass' => __CLASS__
            ,'order' => array('ID' => 'DESC')
        )
    );

     protected static function _defineFields()
     {
         // auto-initialize account levels from User model
        if (empty(self::$fields['RequiredAccountLevel']['values'])) {
            self::$fields['RequiredAccountLevel']['values'] = array_slice(User::$fields['AccountLevel']['values'], array_search('User', User::$fields['AccountLevel']['values']));
        }

         return parent::_defineFields();
     }

     public static function getByHandle($handle)
     {
         return static::getByField('Handle', $handle, true);
     }


     public static function getAllAccessible($options = array())
     {
         global $Session;

         if (empty($options['order'])) {
             $options['order'] = array('Position' => 'DESC');
         }

         $where = 'RequiredAccountLevel IS NULL';

         if ($Session->Person && in_array($Session->Person->AccountLevel, self::$fields['RequiredAccountLevel']['values'])) {
             $where .= ' OR RequiredAccountLevel <= '.(array_search($Session->Person->AccountLevel, self::$fields['RequiredAccountLevel']['values'])+1);
         }

         return static::getAllByWhere($where, $options);
     }


     public function save()
     {
         // set handle
        if (!$this->Handle) {
            $this->Handle = static::getUniqueHandle($this->Title);
        }

        // call parent
        parent::save();
     }
 }