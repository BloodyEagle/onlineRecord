<?php

namespace OnlineRecord;

class Settings {
    
    protected static $settings = array();//массив со значениями настроек
    
    /**=========================================================================
     * загружает все настройки из базы
     */
    public static function init() {
        $query = \QB::table('settings')->select('*')->orderBy('name');
        $result = $query->get();
        foreach ($result as $v) {
            self::$settings[$v->name] = array('value' => $v->value, 'desc' => $v->desc, 'rules' => $v->rules);
        }
    }
        
    /**=========================================================================
     * Возвращает значение настройки
     * @param String $name - название настройки
     * @return String
     */
    public static function getSetting($name) {
        return (isset(self::$settings[$name]) ? self::$settings[$name]['value'] : null);
    }
    
    /**=========================================================================
     * Устанавливает настройку в новое значение
     * @param String $name - название настройки
     * @return bool
     */
    public static function setSetting(String $name, String $value) {
        if (!is_null(\QB::table('settings')->where('name', $name)->update(array('value' => $value))))
            return true;
        else
            return false;
    }
}
