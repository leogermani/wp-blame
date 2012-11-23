<?php
/*
Plugin Name: WP Blame
Plugin URI: 
Description: 
Author: 
Version: 1.0
Author 
*/

class WP_Blame {

    static $table = 'wpblamelog';
    
    static function init() {
        
        add_action('updated_option', array(__CLASS__, 'update_option'), 10, 3);
        add_action('added_option', array(__CLASS__, 'add_option'), 10, 2);
        add_action('deleted_option', array(__CLASS__, 'delete_option'));
        add_action('wp_login', array(__CLASS__, 'login'));
        add_action('admin_head', array(__CLASS__, 'pageview'));
        
    }
    
    static function update_option($option, $oldvalue, $newvalue) {
        
        // log it
        if (!self::is_protected_option($option)) {
            $diff = self::diff($oldvalue, $newvalue);
            self::log($option, $diff, 'update');
        }
        
    }
    
    static function add_option($option, $value) {
        
        // log it
        if (!self::is_protected_option($option))
            self::log($option, $value, 'add');
        
    }
    
    static function delete_option($option) {
        
        // log it
        if (!self::is_protected_option($option))
            self::log($option, '', 'delete');
        
    }
    
    static function is_protected_option($option) {
        return preg_match('/^_.+/', $option);
    }
    
    static function activate() {
        
        // creates table
        global $wpdb;
        
        $create = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}".self::$table." ( 
	           `ID` int(11) NOT NULL auto_increment, 
	           `user_id` int(11), 
	           `option_name` varchar(255), 
	           `value` LONGTEXT, 
               `action` enum('add', 'update', 'delete', 'pageview', 'login', 'logout'),
	           `time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, 
	           PRIMARY KEY (`ID`)) DEFAULT CHARSET=utf8";
        
        $wpdb->query($create);
    }
    
    static function log($option_name, $value, $action) {
        
        global $wpdb;
        
        $user = wp_get_current_user();
        if (is_user_logged_in() && is_object($user))
            $user_id = $user->ID;
        else
            $user_id = 0;
        
        $value = maybe_serialize($value);
        
        
        return $wpdb->insert( $wpdb->prefix . self::$table, array('option_name' => $option_name, 'value' => $value, 'user_id' => $user_id, 'action' => $action) );
        
        
    }

    
    static function login($login) {
        self::log('', '', 'login');
    }
    
    function pageview() {
        global $pagenow;
        
        # We dont want to log when saving anything
        if (isset($_POST) && sizeof($_POST) > 0)
            return;
            
        
        if (!empty($pagenow))
            self::log('', $pagenow, 'pageview');
        
    }
    
    
    static function recursive_newValuearray($obj){
        // se nao é array nem objeto, retorna o valor
        if(!is_array($obj) && !is_object($obj))
            return $obj;
        
        // se for objeto, faz o cast
        elseif(is_object($obj))
            $obj = (array) $obj;
        
        foreach($obj as $key => $val)
            if(is_object($val))
                $obj[$key] = self::recursive_newValuearray ($val);
        
        return $obj;
    }
    static function diff($oldValue, $newValue){
        if($oldValue == $newValue)
            return array();
        
        if(is_object($oldValue))
            $oldValue = (array) $oldValue;
        
        if(is_object($newValue))
            $newValue = (array) $newValue;
        
        
        // se um dos dois objetos não for array, retorna o segundo objeto
        if(!is_array($oldValue) || !is_array($newValue))
            return $newValue;
       
        $result = array();
        foreach($oldValue as $key => $val){
            
            // se a chave não existe no newValue
            if(!isset($newValue[$key])){
                $result[$key] = null;
                
            }elseif(isset($newValue[$key]) && $newValue[$key] != $val){
                $diff = self::diff($val, $newValue[$key]);
                if($diff)
                    $result[$key] = $diff;
            }
            
        }
        
        // coloca no resultado todos os valores do newValue que não existem no oldValue 
        foreach ($newValue as $key => $val)
            if(!isset ($oldValue[$key]))
                $result[$key] = self::recursive_newValuearray($newValue[$key]);
        
        return $result;
    }
    
    

}

WP_Blame::init();

register_activation_hook(__FILE__, 'wp_blame_activate');

function wp_blame_activate() {
    WP_Blame::activate();
}

?>
