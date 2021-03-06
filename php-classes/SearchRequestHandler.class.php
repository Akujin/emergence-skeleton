<?php

class SearchRequestHandler extends RequestHandler
{
    public static $searchClasses = array();
    public static $useBoolean = true;

    public static $userResponseModes = array(
        'application/json' => 'json'
        ,'text/csv' => 'csv'
    );

    public static function handleRequest()
    {
        if (static::peekPath() == 'json') {
            static::$responseMode = static::shiftPath();
        }

        return static::handleSearchRequest();
    }

    public static function handleSearchRequest()
    {
        if (empty($_REQUEST['q'])) {
            return static::throwError('You did not supply any search terms');
        }

        if (!empty($_REQUEST['tag'])) {
            if (!$Tag = Tag::getByHandle($_REQUEST['tag'])) {
                return static::throwNotFoundException('Tag does not exist');
            }
        }

        if (empty(static::$searchClasses)) {
            return static::throwError('No search classes configured for this site');
        }

        $searchResults = array();
        $totalResults = 0;
        /*
        
        // Extra feature. Specify which classes to search for in Request parameter 'searchClasses'
        
        if(!empty($_REQUEST['searchClasses']))
        {
            $classes = explode(',', $_REQUEST['searchClasses']);
            foreach(static::$searchClasses AS $className => $options)
            {
                if(!in_array($className,$classes))
                    unset(static::$searchClasses[$className]);
            }
            
        }
        */
        foreach (static::$searchClasses AS $className => $options) {
            if (is_string($options)) {
                $className = $options;
                $options = array();
            }

            $options = array_merge(array(
                'className' => $className
                ,'fields' => array('Title')
                ,'conditions' => array()
            ), $options);

            if (empty($options['fields'])) {
                continue;
            }

            // parse fields
            $columns = array(
                'fulltext' => array()
                ,'like' => array()
                ,'exact' => array()
                ,'sql' => array()
            );
            foreach ($options['fields'] AS $field) {
                // transform string-only
                if (is_string($field)) {
                    $field = array(
                        'field' => $field
                    );
                }

                // apply defaults
                $field = array_merge(array(
                    'method' => 'fulltext'
                ), $field);

                // sort conditions
                $columns[$field['method']][] = $field['method'] == 'sql' ? $field['sql'] : $className::getColumnName($field['field']);
            }

            // add match conditions
            $escapedQuery = DB::escape($_REQUEST['q']);
            $matchConditions = array();

            if ($columns['fulltext']) {
                $matchConditions[] = sprintf('MATCH (`%s`) AGAINST ("%s" %s)', implode('`,`', $columns['fulltext']), $escapedQuery, static::$useBoolean ? 'IN BOOLEAN MODE' : '');
            }

            if ($columns['like']) {
                $matchConditions[] =
                    '('
                    .join(') OR (', array_map(function($column) use ($escapedQuery) {
                        return sprintf('`%s` LIKE "%%%s%%"', $column, $escapedQuery);
                    }, $columns['like']))
                    .')';
            }

            if ($columns['exact']) {
                $matchConditions[] =
                    '('
                    .join(') OR (', array_map(function($column) use ($escapedQuery) {
                        return sprintf('`%s` = "%s"', $column, $escapedQuery);
                    }, $columns['exact']))
                    .')';
            }

            if ($columns['sql']) {
                $matchConditions[] =
                    '('
                    .join(') OR (', array_map(function($sql) use ($escapedQuery) {
                        return sprintf($sql, $escapedQuery);
                    }, $columns['sql']))
                    .')';
            }



            $options['conditions'][] = join(' OR ', $matchConditions);

            try {
                if (isset($Tag)) {
                    $results = DB::allRecords(
                        'SELECT p.*'
                        .' FROM `tag_items` t'
                        .' INNER JOIN `%s` p ON (p.ID = t.`ContextID`)'
                        .' WHERE t.`TagID` = %u AND t.`ContextClass` = "%s"'
                        .' AND (%s)'
                        , array(
                            $className::$tableName
                            ,$Tag->ID
                            ,$className
                            ,join(') AND (', $className::mapConditions($options['conditions']))
                        )
                    );
                } else {
                    $results = DB::allRecords(
                        'SELECT * FROM `%s` p WHERE (%s)'
                        , array(
                            $className::$tableName
                            ,join(') AND (', $className::mapConditions($options['conditions']))
                        )
                    );
                }
            } catch (TableNotFoundException $e) {
                $results = array();
            }

            $classResults = count($results);
            $totalResults += $classResults;

            $searchResults[$className] = $classResults ? ActiveRecord::instantiateRecords($results) : array();
        }

        //DebugLog::dumpLog();

        static::respond('search', array(
            'data' => $searchResults
            ,'totalResults' => $totalResults
        ));
    }
}