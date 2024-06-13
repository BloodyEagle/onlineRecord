<?php
namespace OnlineRecord;

use OnlineRecord\Error as Error;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
use Pixie\Connection;

/*const RULE_VIEW = 1;//просмотр списка курсов
const RULE_MODERATE = 2;//добавление | правка курсов
const RULE_REPORTS = 4;//доступ к отчетам
const RULE_CATALOGUE = 8;//правка справочников
const RULE_CATEGORY = 16;//правка дерева категорий курсов
const RULE_DOCS = 32;//доступ к приказам
const RULE_ADMIN =  128;//права админа*/

/*const APP_ALL = 0;//Все заявки на курсы

// данные в базе. При изменениях в базе исправить и тут
const APP_STATUS_NEW = 1;//             На рассмотрении
const APP_STATUS_ACCEPTED = 2;//	Зачислен на курс
const APP_STATUS_REJECTED = 3;//	Отклонена
const APP_STATUS_ENDCOURSE = 4;//	Курс окончен
const APP_STATUS_REVOKED = 5;//         Отозвана заявителем
const APP_STATUS_EXPULSION = 6;//	Отчислен

const APP_MODE_STUDENT = 1;//	Заявки студентов
const APP_MODE_MODERATOR = 2;// Заявки модераторов
const APP_MODE_ADMIN = 3;//	Заявки все

const READY_PASSPORT = 1;//Загружен хотя бы один паспорт
const READY_DIPLOM = 2;//Загружен хотя бы один диплом
const READY_WORK = 3;//Загружены данные хотя бы одного места работы
const READY_ALL = 4;//Все данные Загружены*/

class LKiom
{
    protected $connection;

    private $error = false;
    private $error_message = null;
    private $authorized = false;
        
    private  $Ghtml;
    protected $templates = array();
    //protected $settings;

    private $files = array();
    private $added = array('passport' => false, 'diplom' => false, 'work' => false);
    private $user;
    private $pass;
    private $diplom;
    private $job;
    private $messages;
    private $application;

    private $message_count = 0;
    private $new_message_count = 0;
    private $applicationCount;
    private $appChangedCount;
    private $appModerCount;

    private $codeAdded = false;

    public function __construct(){
        $this->templates = array('{%HEADERS%}' => null,
                                 '{%TITLE%}' => null,
                                 '{%PROFILE%}' => null,
                                 '{%JSCODE%}' => null,
                                 '{%JQCODE%}' => null,
                                 '{%ERRORS%}' => null,
                                 '{%SHOWERRORS%}' => 'style="display: none;"',
                                 '{%CONTENT%}' => null,
                                 '{%MODAL CONTENT%}' => null );
    }

    function __destruct()
    {}
    
    //========================================================================================================================================
    public function isAuthorized() {
        return $this->authorized;
    }
        
    /** ========================================================================================================================================
     * разлогинивает пользователя
     */
    public function logout() {
        setcookie('onlineRecord', 0, time()-1000, '/', $_SERVER['SERVER_NAME']);
        $this->addRedirect(SITE, 0);
    }
    
    /**========================================================================================================================================
     * Поднимает флаг ошибки и записывает ошибку в буфер
     */
    public function setError($msg) {
        $this->error = true;
        $this->templates['{%SHOWERRORS%}'] = 'style="display: block;"';
        $this->templates['{%ERRORS%}'] .= (is_null($this->templates['{%ERRORS%}']) ? $msg : '<br />'.$msg);

    }
    
    /**========================================================================================================================================
     * Устанавливает значение тега <title>
     */
    public function setTitle($msg) {
        $this->templates['{%TITLE%}'] = $msg;
    }
    
    /** ========================================================================================================================================
     * Добавляет HTML заголовок в секцию <HEAD>
     */
    public function addHeader($msg) {
        $this->templates['{%HEADERS%}'] .= $msg;
    }
    
    /** ========================================================================================================================================
     * Добавляет редирект на страницу
     * @param String $address - адрес, на который делается редирект
     * @param int $time - задержка, сек
     */ 
    public function addRedirect($address, $time) {
        $this->addHeader('<meta http-equiv="refresh" content="'.(int)$time.'; URL='.$address.'" />');
    }
    
    
    /** ========================================================================================================================================
     * Добавляет контент в основной блок страницы
     * @param string $msg - контент
     */
    public function addContent($msg) {
        $this->templates['{%CONTENT%}'] .= $msg;
    }
    
    
    /** ========================================================================================================================================    
     * Добавляет шаблон для замены $search на $replace на странице
     * @param string $search - строка поиска
     * @param string $replace - строка замены
     */
    public function addRtemplate($search, $replace) {
        $this->templates[$search] = $replace;
    }

    public function addModalContent($content) {
        $this->addRtemplate('{%MODAL CONTENT%}', $content);
    }
    
    /** ========================================================================================================================================
     * Добавляет нативный JavaScript код в <head> секцию
     * @param string $msg - строка кода
     */
    public function addJSCode($msg) {
        $this->codeAdded = true;
        $this->templates['{%JSCODE%}'] .= $msg;
    }

    /** ========================================================================================================================================
     * Добавляет jQuery код  в <head> секцию внутрь блока $(document).ready
     * @param string $msg - строка кода
     */
    public function addJQCode($msg) {
        $this->codeAdded = true;
        $this->templates['{%JQCODE%}'] .= $msg."\n";
    }
    
    
    /** ========================================================================================================================================
     * Генерирует сообщение для пользователя
     * @param String $ttl - заголовок сообщения
     * @param String $msg - текст сообщения
     * @param String $link - ссылка
     * @param Int $owner - ID пользователя, для кого сообщение
     * @return boolean - true если все ок
     */
    public function addMessage($ttl, $msg, $link = null, $owner = null) {
        $data = array(
            'title' => $ttl,
            'message' => $msg,
            'link' => (is_null($link) ? 'NULL' : $link),
            'owner' => (is_null($owner) ? $this->user->id : $owner)
        );
        
        $insertId = \QB::table('messaegs')->insert($data);
        if(is_null($insertId)){
            $this->addContent('Ошибка записи сообщения в базу!');
            return false;
        }
    }
    

    /** ========================================================================================================================================
     * Выводит в контент форму для входа
     */
    public function loginForm() {
        $this->addContent(file_get_contents(dirname(__FILE__).'/loginForm.html'));
        $this->addRtemplate('{%CATID%}', (isset($_REQUEST['cat']) ? $_REQUEST['cat'] : ''));
        $this->setTitle('Форма авторизации');
        //$this->parseHtml($this->templates);
        
    }
    
    /** ========================================================================================================================================
     * Выводит в контент форму для восстановления пароля
     */
    public function lostPassForm() {
        $html = file_get_contents(dirname(__FILE__).'/lostpassForm.html');
        $js = file_get_contents(dirname(__FILE__).'/lostpassForm.js');
        $jqs = file_get_contents(dirname(__FILE__).'/lostpassForm.jqs');
        $this->setTitle('Восстановление пароля');
        $this->addContent($html);
        $this->addJSCode($js);
        $this->addJQCode($jqs);
        
    }
     
    /** ========================================================================================================================================
     * Выводит в контент форму для регистрации
     */
    public function regForm() {
        $this->addRtemplate('{%REGIONLIST%}', $this->getRegions());
        $this->addRtemplate('{%WREGIONLIST%}', $this->getRegions(null, true));
        $this->addRtemplate('{%SEXLIST%}', $this->getSex());
        $this->addRtemplate('{%REGRMLIST%}', $this->getDistinct());
        $this->addRtemplate('{%WREGRMLIST%}', $this->getDistinct(null, true));
        $this->addRtemplate('{%CITIZENSHIPLIST%}', $this->getCountries());
        $this->addRtemplate('{%EDULEVELLIST%}', $this->getEdulevel());
        $this->addRtemplate('{%REQUIRED PASSPORT SCAN%}', Settings::getSetting('requiredPassportScan') == 1 ? 'class="rfield"' : '');        
        $this->addRtemplate('{%REQUIRED DIPLOM SCAN%}', Settings::getSetting('requiredDiplomScan') == 1 ? 'class="rfield"' : '');        
        $this->addRtemplate('{%REQUIRED SNILS SCAN%}', Settings::getSetting('requiredSnilsScan') == 1 ? 'class="rfield"' : '');        
        $this->addRtemplate('{%REQUIRED SNILS MARK%}', Settings::getSetting('requiredSnilsScan') == 1 ? '<span style="color: red;">*</span>' : '');
        $this->addRtemplate('{%REQUIRED PASSPORT MARK%}', Settings::getSetting('requiredPassportScan') == 1 ? '<span style="color: red;">*</span>' : '');
        $this->addRtemplate('{%REQUIRED DIPLOM MARK%}', Settings::getSetting('requiredDiplomScan') == 1 ? '<span style="color: red;">*</span>' : '');
        $this->addRtemplate('{%HIDE FIELD SNILS SCAN%}', Settings::getSetting('hideFieldSnilsScan') == 1 ? 'hidden' : ''); 
        $this->setTitle('Регистрация нового пользователя');
        
        $this->addHeader('<link rel="stylesheet" href="/inc/select2.css">');
        $this->addHeader('<script src="/inc/select2.full.js"></script>');
        $js ="$('#region').select2({
		placeholder: 'Выберите регион',
		language: 'ru'
                });
              $('#regionrm').select2({
		placeholder: 'Выберите район',
		language: 'ru'
                });
              $('#wregion').select2({
		placeholder: 'Выберите регион',
		language: 'ru'
                });
              $('#wregionrm').select2({
		placeholder: 'Выберите район',
		language: 'ru'
                });  
            /** Костыль для автофокуса поля ввода в выпадающем списке 
             *  перестало работатть на новой jQuery
             *  когда разрабы пофиксят баг, можно убрать */
            jQuery(document).on('select2:open', function (e) {
                    window.setTimeout(function() {
                        jQuery('.select2-container--open .select2-search__field').get(0).focus();
                    }, 200);
                });
            /* конец костыля ... */ ";
        $this->addJQCode($js);
        
        $this->addContent(file_get_contents(dirname(__FILE__).'/regForm.html'));
        //$this->parseHtml($this->templates);
    }
    
    /** ========================================================================================================================================
     * Выводит в контент форму для добавления\сохранения данных пользовтаеля
     * @param type $id - ID пользователя. Указывается только для редактирования 
     * @return boolean
     */
    public function userInfo($id = null) {
        if (!$this->isAuthorized()){
            $this->setError('Вы не авторизованы! Сначала <a href="/iom/?act=loginform">войдите под своей учетной записью</a>!');
            return false;
        }
        $edit = false;
        if (!is_null($id)){
            $edit = true;
            $query = \QB::table('users')->select(array(
                                            'users.id', 'users.snils','users.extfile', 
                                            'users.group', \QB::raw(\OnlineRecord\config['prefix']."ugroup.group AS groupR"), 
                                            'users.rules', \QB::raw(\OnlineRecord\config['prefix']."ugroup.rules AS grules"), 
                                            'users.added', 'users.closed', 
                                            'users.firstname', 'users.lastname', 'users.fathername', 
                                            'users.sex', //\QB::raw(\OnlineRecord\config['prefix']."sex.sex AS sexR"), 
                                            \QB::raw("DATE_FORMAT(".\OnlineRecord\config['prefix']."users.birthday, '%d.%m.%Y') AS birthday"), 
                                            'users.phone', 'users.email', 'users.pedstage', 'users.password', 
                                            'users.region', //\QB::raw(\OnlineRecord\config['prefix']."regions.region AS regionR"), 
                                            'users.city', 
                                            'users.distinctrm', //\QB::raw(\OnlineRecord\config['prefix']."distinct.distinct AS distinctrmR"), 
                                            'users.distinct',
                                            'users.address',
                                            'users.cityrm', 'users.checked', 'users.activated', 'users.banned'))
                    ->leftJoin('sex', 'sex.id', '=', 'users.sex')
                    ->leftJoin('regions', 'regions.id', '=', 'users.region')
                    ->leftJoin('distinct', 'distinct.id', '=', 'users.distinctrm')
                    ->leftJoin('ugroup', 'ugroup.id', '=', 'users.group')
                            ->where('users.id',  $id)
                            ->where('activated', '=', 1);
                            //->where('banned', '=', 0);
            $r = $query->first();
            //$this->addJQCode('$(".formrow input[type=text], select").after("<input type=\"checkbox\" style=\"position: absolute; margin-left: 17%; \">");');
            $this->addJQCode('$("#id").after("<input type=\"hidden\" name=\"moder\" value=\"1\">").after("<input type=\"hidden\" name=\"referer\" value=\"'.(isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : $_REQUEST['referer']).'\">");');
            $this->addJQCode('$("#save").val("Сохранить данные").after("<input type=\"submit\" name=\"check\" id=\"check\" value=\"Подтвердить данные\">");');
            $this->addJQCode('$("#check").on("click", function(){ location.href="/iom/?act=checkdata&user='.$r->id.'&referer='.(isset($_REQUEST['referer']) ? $_REQUEST['referer'] : $_SERVER['HTTP_REFERER']).'"; return false; });');
        }
        
        $this->addRtemplate('{%USERID%}', $edit ? $r->id : $this->user->id);
        $this->addRtemplate('{%LASTNAME%}', $edit ? $r->lastname : $this->user->lastname);
        $this->addRtemplate('{%FIRSTNAME%}', $edit ? $r->firstname : $this->user->firstname);
        $this->addRtemplate('{%FATHERNAME%}', $edit ? $r->fathername : $this->user->fathername);
        $this->addRtemplate('{%SNILS%}', $edit ? $r->snils : $this->user->snils);
        $this->addRtemplate('{%SSCAN%}', (is_null($edit ? $r->extfile : $this->user->extfile) ? '<span class="wrong">Скан СНИЛС не был прикреплен!</span>' : 
                                'Скан СНИЛС: <a href="/iom/?act=download&file=snils_'.($edit ? $r->id : $this->user->id).'.'.($edit ? $r->extfile : $this->user->extfile)
                                .'"target="_blank"><img src="/img/snils.png"></a><label><input type="checkbox" id="changescan">Заменить скан</label>'));
        $this->addRtemplate('{%BIRTHDAY%}', $edit ? $r->birthday : $this->user->birthday);
        $this->addRtemplate('{%CITY%}', $edit ? $r->city : $this->user->city);
        $this->addRtemplate('{%STAGE%}', $edit ? $r->pedstage : $this->user->pedstage);
        $this->addRtemplate('{%PHONE%}', $edit ? $r->phone : $this->user->phone);
        $this->addRtemplate('{%DISTINCT%}', $edit ? $r->distinct : $this->user->distinct);
        $this->addRtemplate('{%EMAIL%}', $edit ? $r->email : $this->user->email);
        $this->addRtemplate('{%CITYRM%}', (($edit ? $r->cityrm : $this->user->cityrm) == 1 ? '' : 'style="display: none;"'));
        $this->addRtemplate('{%CITYBOX%}', (($edit ? $r->cityrm : $this->user->cityrm) == 1 ? 'style="display: none;"' : ''));
        $this->addRtemplate('{%REGIONRM%}', (($edit ? $r->region : $this->user->region) == 13 ? '' : 'style="display: none;"'));
        $this->addRtemplate('{%CITY%}', $edit ? $r->city : $this->user->city);
        $this->addRtemplate('{%ADDRESS%}', $edit ? $r->address : $this->user->address);
        if (($edit ? $r->cityrm : $this->user->cityrm) == 1) $this->addJQCode("$('#cityrm').prop('checked', true);\n");
        if (!is_null($edit ? $r->extfile : $this->user->extfile))$this->addJQCode(file_get_contents(dirname(__FILE__).'/passInfo.jqs'));
        $this->addRtemplate('{%REGIONLIST%}', $this->getRegions($edit ? $r->region : $this->user->region));
        $this->addRtemplate('{%SEXLIST%}', $this->getSex($edit ? $r->sex : $this->user->sex));
        $this->addRtemplate('{%REGRMLIST%}', $this->getDistinct($edit ? $r->distinctrm : $this->user->distinctrm));
        $this->addContent(file_get_contents(dirname(__FILE__).'/userInfo.html'));
        
        $this->addHeader('<link rel="stylesheet" href="/inc/select2.css">');
        $this->addHeader('<script src="/inc/select2.full.js"></script>');
        $js ="$('#region').select2({
		placeholder: 'Выберите регион',
		language: 'ru'
                });
              $('#regionrm').select2({
		placeholder: 'Выберите район',
		language: 'ru'
                });
              
            /** Костыль для автофокуса поля ввода в выпадающем списке 
             *  перестало работатть на новой jQuery
             *  когда разрабы пофиксят баг, можно убрать */
            jQuery(document).on('select2:open', function (e) {
                    window.setTimeout(function() {
                        jQuery('.select2-container--open .select2-search__field').get(0).focus();
                    }, 200);
                });
            /* конец костыля ... */ ";
        $this->addJQCode($js);
    }
    
    /** ========================================================================================================================================
     * Выводит в контент форму для добавления\сохранения данных паспорта пользовтаеля
     * @param type $id - ID ПАСПОРТА. Указывается только для редактирования 
     * @return boolean
     */
    public function passInfo($id = null) {
        if (!$this->isAuthorized()){
            $this->setError('Вы не авторизованы! Сначала <a href="/iom/?act=loginform">войдите под своей учетной записью</a>!');
            return false;
        }
        Error::pdump($id, 'id');
        $edit = false;
        if (!is_null($id)){
            $edit = true;
            foreach ($this->pass as $r) {
                if ($r->id == $id){
                    Error::pdump($r, 'r');
                    break;
                }
            }
            if ($r->id != $id){
                Error::pdump('Паспорт не мой');
                $query = \QB::table('pass')->select(array(
                                                'pass.id', 
                                                \QB::raw(\OnlineRecord\config['prefix']."pass.citizenship AS citizen"), 
                                                'pass.series', 
                                                'pass.number', 
                                                \QB::raw("DATE_FORMAT(".\OnlineRecord\config['prefix']."pass.datedoc, '%d.%m.%Y') AS datedoc"), 
                                                'pass.info', 
                                                'pass.extfile', 
                                                'pass.parent', 
                                                'pass.checked'))
                                ->where('pass.id',  $id);
                $r = $query->first();
                $this->addJQCode('$("#id").after("<input type=\"hidden\" name=\"moder\" value=\"1\">").after("<input type=\"hidden\" name=\"referer\" value=\"'.(isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : $_REQUEST['referer']).'\">");');
                $this->addJQCode('$("#save").val("Сохранить данные").after("<input type=\"submit\" name=\"check\" id=\"check\" value=\"Подтвердить данные\">");');
                $this->addJQCode('$("#check").on("click", function(){ location.href="/iom/?act=checkdata&pass='.$r->id.'&referer='.(isset($_REQUEST['referer']) ? $_REQUEST['referer'] : $_SERVER['HTTP_REFERER']).'"; return false; });');
            }
        }
        $this->addRtemplate('{%PASSID%}', !is_null($id) ? $r->id : '');;
        $this->addRtemplate('{%CITIZENSHIPLIST%}', !is_null($id) ? $this->getCountries($r->citizen) : $this->getCountries());
        $this->addRtemplate('{%PSERIES%}', !is_null($id) ? $r->series : '');
        $this->addRtemplate('{%PNUMBER%}', !is_null($id) ? $r->number : '');
        $this->addRtemplate('{%PDATE%}', !is_null($id) ? $r->datedoc : '');
        $this->addRtemplate('{%PINFO%}', !is_null($id) ? $r->info : '');
        $this->addRtemplate('{%PSCAN%}', !is_null($id) ? (is_null($r->extfile) ? 'Скан паспорта не был прикреплен!' : 
                            'Скан паспорта: <a href="/iom/?act=download&file=pass_'.$r->id.'.'.$r->extfile
                            .'"target="_blank"><img src="/img/passport.png"></a><label><input type="checkbox" id="changescan">Заменить скан</label>') : '');
        if (!is_null($id)){
            if (!is_null($r->extfile))$this->addJQCode(file_get_contents(dirname(__FILE__).'/passInfo.jqs'));
        }
        $this->addContent(file_get_contents(dirname(__FILE__).'/passInfo.html'));
    }
    
    /** ========================================================================================================================================
     * Выводит в контент форму для добавления\сохранения данных о работе пользовтаеля
     * @param type $id - ID работы. Указывается только для редактирования 
     * @return boolean
     */
    public function workInfo($id = null) {
        if (!$this->isAuthorized()){
            $this->setError('Вы не авторизованы! Сначала <a href="/iom/?act=loginform">войдите под своей учетной записью</a>!');
            return false; 
        }
        $edit = false;
        if (!is_null($id)){
            $edit = true;
            foreach ($this->job as $r) {
                if ($r->workid == $id)
                    break;
            }
            if ($r->workid != $id){
                Error::pdump('Работа не моя');
                $r = \QB::table('work')->find($id);
                $r->workid = $r->id;
                //$r = $query->first();
                $this->addJQCode('$("#id").after("<input type=\"hidden\" name=\"moder\" value=\"1\">").after("<input type=\"hidden\" name=\"referer\" value=\"'.(isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : $_REQUEST['referer']).'\">");');
                $this->addJQCode('$("#save").val("Сохранить данные").after("<input type=\"submit\" name=\"check\" id=\"check\" value=\"Подтвердить данные\">");');
                $this->addJQCode('$("#check").on("click", function(){ location.href="/iom/?act=checkdata&work='.$r->id.'&referer='.(isset($_REQUEST['referer']) ? $_REQUEST['referer'] : $_SERVER['HTTP_REFERER']).'"; return false; });');
            }
        }
        //Error::pdump($r, 'work');
        $this->addRtemplate('{%WORKID%}', !is_null($id) ? $r->workid : '');
        $this->addRtemplate('{%ORGANISATION%}', !is_null($id) ? $r->organisation : '');
        
        //$this->addRtemplate('{%REGIONRM%}', ($this->user->region == 13 ? '' : 'style="display: none;"'));
        $this->addRtemplate('{%WCITY%}', !is_null($id) ? $r->city : '');
        $this->addRtemplate('{%WREGIONLIST%}', $this->getRegions(!is_null($id) ? $r->region : null, true));
        $this->addRtemplate('{%WREGRMLIST%}', $this->getDistinct(!is_null($id) ? $r->distinctrm : null, true));
        $this->addRtemplate('{%WADDRESS%}', !is_null($id) ? $r->waddress : '');
        $this->addRtemplate('{%PROFESSION%}', !is_null($id) ? $r->profession : '');
        $this->addRtemplate('{%STAGE%}', !is_null($id) ? $r->stage : '');
        $this->addRtemplate('{%GOSSLUJBA%}', !is_null($id) ? ($r->gosslujba == 1 ? 'checked="checked"' : '') : '');
        $this->addRtemplate('{%WORKPHONE%}', !is_null($id) ? $r->phone : '');
        $this->addContent(file_get_contents(dirname(__FILE__).'/workInfo.html'));
        
        $this->addHeader('<link rel="stylesheet" href="/inc/select2.css">');
        $this->addHeader('<script src="/inc/select2.full.js"></script>');
        $js ="$('#wregion').select2({
		placeholder: 'Выберите регион',
		language: 'ru'
                });
              $('#wregionrm').select2({
		placeholder: 'Выберите район',
		language: 'ru'
                });
              
            /** Костыль для автофокуса поля ввода в выпадающем списке 
             *  перестало работатть на новой jQuery
             *  когда разрабы пофиксят баг, можно убрать */
            jQuery(document).on('select2:open', function (e) {
                    window.setTimeout(function() {
                        jQuery('.select2-container--open .select2-search__field').get(0).focus();
                    }, 200);
                });
            /* конец костыля ... */ ";
        $this->addJQCode($js);
    }
    
    /** ========================================================================================================================================
     * Выводит в контент форму для добавления\сохранения данных диплома пользовтаеля
     * @param type $id - ID диплома. Указывается только для редактирования 
     * @return boolean
     */
    public function diplomInfo($id = null) {
        if (!$this->isAuthorized()){
            $this->setError('Вы не авторизованы! Сначала <a href="/iom/?act=loginform">войдите под своей учетной записью</a>!');
            return false;
        }
        $edit = false;
        if (!is_null($id)){
            $edit = true;
            foreach ($this->diplom as $r) {
                if ($r->id == $id)
                    break;
            }
            if ($r->id != $id){
                Error::pdump('Диплом не мой');
                $query = \QB::table('diplom')->select('*')->select(\QB::raw("DATE_FORMAT(".\OnlineRecord\config['prefix']."diplom.datedoc, '%d.%m.%Y') AS datedoc"))->where('diplom.id', $id);
                $r = $query->first();
                Error::pdump($r, "r");
                
                $this->addJQCode('$("#id").after("<input type=\"hidden\" name=\"moder\" value=\"1\">").after("<input type=\"hidden\" name=\"referer\" value=\"'.(isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : $_REQUEST['referer']).'\">");');
                $this->addJQCode('$("#save").val("Сохранить данные").after("<input type=\"submit\" name=\"check\" id=\"check\" value=\"Подтвердить данные\">");');
                $this->addJQCode('$("#check").on("click", function(){ location.href="/iom/?act=checkdata&diplom='.$r->id.'&referer='.(isset($_REQUEST['referer']) ? $_REQUEST['referer'] : $_SERVER['HTTP_REFERER']).'"; return false; });');
            }
        }
        $this->addRtemplate('{%DIPLOMID%}', !is_null($id) ? $r->id : '');
        $this->addRtemplate('{%EDULEVELLIST%}', !is_null($id) ? $this->getEdulevel($r->edu_level) : $this->getEdulevel());
        $this->addRtemplate('{%ALMA MATTER%}', !is_null($id) ? $r->almamatter : '');
        $this->addRtemplate('{%DSERIES%}', !is_null($id) ? $r->series : '');
        $this->addRtemplate('{%DNUMBER%}', !is_null($id) ? $r->number : '');
        $this->addRtemplate('{%DREGNUMBER%}', !is_null($id) ? $r->regnumber : '');
        $this->addRtemplate('{%DDATE%}', !is_null($id) ? $r->datedoc : '');
        $this->addRtemplate('{%DQUALIFICATION%}', !is_null($id) ? $r->qualification : '');
        $this->addRtemplate('{%DSTEPEN%}', !is_null($id) ? $r->stepen : '');
        $this->addRtemplate('{%DZVANIE%}', !is_null($id) ? $r->zvanie : '');
        $this->addRtemplate('{%FIOCHECKED%}', !is_null($id) ? ( (is_null($r->f) && is_null($r->i) && is_null($r->o)) ? '' : 'checked="checked"') : '');
        $this->addRtemplate('{%DF%}', !is_null($id) ? $r->f : '');
        $this->addRtemplate('{%DI%}', !is_null($id) ? $r->i : '');
        $this->addRtemplate('{%DO%}', !is_null($id) ? $r->o : '');
        $this->addRtemplate('{%FSCAN%}', !is_null($id) ? (is_null($r->fextfile) ? '<p class="c" style="color: red">Скан документа о смене ФИО не был прикреплен!</p>' : 
                            'Скан документа о смене ФИО: <a href="/iom/?act=download&file=fio_'.$r->id.'.'.$r->fextfile
                            .'"target="_blank"><img src="/img/fio.png"></a><label><input type="checkbox" id="changefscan">Заменить скан</label>') : '');
            if (!is_null($id))
                if (!is_null($r->fextfile)) $this->addJQCode("$('#ffield').hide();");
        $this->addRtemplate('{%DSCAN%}', !is_null($id) ? (is_null($r->dextfile) ? '<p class="c" style="color: red">Скан диплома не был прикреплен!</p>' : 
                            'Скан диплома: <a href="/iom/?act=download&file=diplom_'.$r->id.'.'.$r->dextfile
                            .'"target="_blank"><img src="/img/diplom.png"></a><label><input type="checkbox" id="changescan">Заменить скан</label>') : '');
            if (!is_null($id))
                if (!is_null($r->dextfile))$this->addJQCode(file_get_contents(dirname(__FILE__).'/diplomInfo.jqs'));
        $this->addContent(file_get_contents(dirname(__FILE__).'/diplomInfo.html'));
    }
    
    /** ========================================================================================================================================
     * Выводит в контент форму регистрации пользовтаеля
     */
    public function register() {
        if (!isset($_REQUEST['accept'])){
            $this->setError('Необходимо сщгласиться с политикой обработки персональных данных!');
            return false;
        }
        
        if ($_REQUEST['pass1'] <> $_REQUEST['pass2']){
            $this->setError('Введеные пароли не совпадают!');
            return false;
        }
        
        $err = '';
        if (!validateSnils($_REQUEST['snils'], $err)){
            $this->setError($err);
            return false;
        }
        
        $query = \QB::table('users')->where('snils', '=', trim($_REQUEST['snils']))
                                ->orWhere('phone', '=', trim($_REQUEST['phone']))->orWhere('email', '=', trim($_REQUEST['email']));
        $result = $query->count();
        
        if ($result != 0){
            $this->setError('Пользователь с такими данными (СНИЛС, телефон или e-mail) уже существует! Вы можете иметь только одну '
                    . 'учетную запись на сайте.<br />Войдите со своими учетными данными или проверьте введённые данные. '
                    . 'Если вы не помните пароль, воспользуйтесь <a href="/iom/?act=recpassform">формой восстановления пароля</a>.');
            return false;
        }
        
        $dt = \DateTime::createFromFormat('d.m.Y', $_REQUEST['birthday']);
        
        $data = array(
            'snils' => trim($_REQUEST['snils']),
            'lastname' => trim($_REQUEST['lastname']),
            'firstname' => trim($_REQUEST['firstname']),
            'fathername' => trim($_REQUEST['fathername']),
            'sex' => $_REQUEST['sex'],
            'birthday' =>  $dt->format('Y-m-d'),
            'phone' => trim($_REQUEST['phone']),
            'email' => trim($_REQUEST['email']),
            'pedstage' => $_REQUEST['stage'],
            'password' => password_hash($_REQUEST['pass1'], PASSWORD_BCRYPT),
            'region' => $_REQUEST['region'],
            'city' => trim($_REQUEST['city']),
            'distinctrm' => ($_REQUEST['region'] == 13 ? $_REQUEST['regionrm'] : null),
            'distinct' => ($_REQUEST['region'] != 13 ? $_REQUEST['distinct'] : null),
            'address' => trim($_REQUEST['address']),
            'cityrm' => (isset($_REQUEST['cityrm']) ? 1 : 0)
        );
        $insertId = \QB::table('users')->insert($data);
        Error::pdump('Пишу чела в базу, id - '.$insertId);
        
        if (count($_FILES) != 0){
            $extfile = substr(strrchr($_FILES['sscan']['name'], '.'), 1);
            $uploadfile = uploaddir . 'snils_' . $insertId.'.'.$extfile;
                if (move_uploaded_file($_FILES['sscan']['tmp_name'], $uploadfile)) {
                    Error::pdump('Скан СНИЛС загружен');
                    $data = array(
                        'extfile' => $extfile
                    );
                    $query = \QB::table('users')->where('id', '=', $insertId)->update($data);
                    Error::pdump('и записан в базу');
                } else {
                    Error::pdump('Ошибка загрузки скана СНИЛМ.');
                    $this->setError('Скан СНИЛС загрузить не удалось.');
                }
            if(is_null($insertId)){
                $this->setError('Ошибка записи данных пользователя в базу!');
                return false;
            }
        }
        if (isset($_REQUEST['addpass'])){
            
            $dt = \DateTime::createFromFormat('d.m.Y', $_REQUEST['pdata']);
            $data = array(
                'citizenship' => $_REQUEST['citizenship'],
                'series' => trim($_REQUEST['pseries']),
                'number' => trim($_REQUEST['pnumber']),
                'datedoc' => $dt->format('Y-m-d'),
                'info' => $_REQUEST['pvidan'],
                'parent' => $insertId,
                'used' => 1
            );
            $insertIdpass = \QB::table('pass')->insert($data);
            Error::pdump('Пишу паспорт в базу, id - '.$insertIdpass);
            $extfile = substr(strrchr($_FILES['pscan']['name'], '.'), 1);
            
            $uploadfile = uploaddir . 'pass_' . $insertIdpass.'.'.$extfile;
            if (move_uploaded_file($_FILES['pscan']['tmp_name'], $uploadfile)) {
                Error::pdump('Скан паспорта загружен');
                $data = array(
                    'extfile' => $extfile
                );
                $query = \QB::table('pass')->where('id', '=', $insertIdpass)->update($data);
                Error::pdump('и записан в базу');
            } else {
                Error::pdump('Ошибка загрузки скана паспорта.');
                $this->setError('Скан паспорта загрузить не удалось.');
            }
        }//if (isset($_REQUEST['addpass']))

        if (isset($_REQUEST['adddiplom'])){
            $dt = \DateTime::createFromFormat('d.m.Y', $_REQUEST['ddata']);
            $data = array(
                'edu_level' => $_REQUEST['edulevel'],
                'almamatter' => trim($_REQUEST['almamatter']),
                'series' => trim($_REQUEST['dseries']),
                'number' => trim($_REQUEST['dnumber']),
                'regnumber' => trim($_REQUEST['regnumber']),
                'datedoc' => $dt->format('Y-m-d'),
                'qualification' => $_REQUEST['qualification'],
                'stepen' => $_REQUEST['stepen'],
                'zvanie' => $_REQUEST['zvanie'],
                'f' => ($_REQUEST['dlastname'] == ''? NULL : $_REQUEST['dlastname']),
                'i' => ($_REQUEST['dfirstname'] == ''? NULL : $_REQUEST['dfirstname']),
                'o' => ($_REQUEST['dfathername'] == ''? NULL : $_REQUEST['dfathername']),
                'parent' => $insertId
            );
            $insertIddiplom = \QB::table('diplom')->insert($data);
            Error::pdump('Пишу диплом в базу, id - '.$insertIddiplom);
            $extfile = substr(strrchr($_FILES['dscan']['name'], '.'), 1);
            $uploadfile = uploaddir . 'diplom_' . $insertIddiplom.'.'.$extfile;
            if (move_uploaded_file($_FILES['dscan']['tmp_name'], $uploadfile)) {
                Error::pdump('Скан диплома загружен');
                $data = array(
                    'dextfile' => $extfile
                );
                $query = \QB::table('diplom')->where('id', '=', $insertIddiplom)->update($data);
                Error::pdump('и записан в базу');
            } else {
                Error::pdump('Ошибка загрузки скана диплома.');
                $this->setError('Скан диплома загрузить не удалось.');
            }
            $extfile = substr(strrchr($_FILES['fscan']['name'], '.'), 1);
            $uploadfile = uploaddir . 'fam_' . $insertIddiplom.'.'.$extfile;
            if (move_uploaded_file($_FILES['fscan']['tmp_name'], $uploadfile)) {
                Error::pdump('Скан документа о смене ФИО загружен');
                $data = array(
                    'fextfile' => $extfile
                );
                $query = \QB::table('diplom')->where('id', '=', $insertIddiplom)->update($data);
                Error::pdump('и записан в базу');
            } else {
                Error::pdump('Ошибка загрузки скана документа о смене фамилии.');
                $this->setError('Скан документа о смене фамилии загрузить не удалось.');
            }
        }//if (isset($_REQUEST['adddiplom']))
        
        if (isset($_REQUEST['addwork'])){
            
            $data = array(
                'organisation' => trim($_REQUEST['organisation']),
                'region' => trim($_REQUEST['wregion']),
                'distinctrm' => trim($_REQUEST['wregionrm']),
                'city' => trim($_REQUEST['wcity']),
                'profession' => trim($_REQUEST['dolgnost']),
                'stage' => trim($_REQUEST['wstage']),
                'gosslujba' => (isset($_REQUEST['gosslujba']) ? 1 : 0),
                'phone' => $_REQUEST['wphone'],
                'waddress' => $_REQUEST['waddress'],
                'parent' => $insertId
            );
            $insertIdwork = \QB::table('work')->insert($data);
            Error::pdump('Пишу данные о работе в базу, id - '.$insertIdwork);
        }//if (isset($_REQUEST['addwork']))
        
        $this->sendActivationMail($_REQUEST['email']);
    }
    
    /** =================================================================================================================================================
     * Высылаем письмо с активационным кодом для подтверждения регистрации
     * @param String $email
     */
    public function sendActivationMail($email) {
        $body = '<p>Здравствуйте!<br />Ваш e-mail был указан при регистрации личного кабинета на сайте Педагог 13.ру.</p>'.
                '<p>Если вы этого не делали, просто проигнорируйте это письмо.</p>'.
                'Если же заявку на регистрацию подавали вы, то <a href="'.SITE.'/iom/?act=activation&id='. 
                urlencode($this->encrypt($email)).'">перейдите по ссылке</a> для подтверждения регистрации.</p>'
                . '<p>Если у вас нет возможности перейти по ссылке, можете скопировать ссылку ниже и вставить ее в браузер для подтверждения аккаунта:<br />'
                .''. SITE.'/iom/?act=activation&id=' . urlencode($this->encrypt($email)).'</p>'
                .'<p></p><p>Это письмо сгенерировано автоматически, отвечать на него не нужно - письмо ни до кого не дойдет!</p>';
        
        $this->sendMail($email, 
                        'Подтверждение регистрации на сайте Педагог 13.ру', 
                        $body, 
                        'Вам выслано письмо с подтверждением регистрации.<br />Пожалуйста, проверьте свою почту.');
        $this->addRedirect(SITE.'/iom/?act=loginform', 10);
    }
    
    /** ===================================================================================================================
     * Сохраняет данные пользователя
     * @return boolean
     */
    public function saveUser($moder = false) {
        if (!$this->isAuthorized()){
            $this->setError('Вы не авторизованы! Сначала <a href="/iom/?act=loginform">войдите под своей учетной записью</a>!');
            return false;
        }
        if (isset($_REQUEST['changepass']))
        if ($_REQUEST['pass1'] <> $_REQUEST['pass2']){
            $this->setError('Введеные пароли не совпадают!');
            return false;
        }
        
        $err = '';
        if (!validateSnils($_REQUEST['snils'], $err)){
            $this->setError($err);
            return false;
        }
        
        $dt = \DateTime::createFromFormat('d.m.Y', $_REQUEST['birthday']);
        
        $data = array(
            'snils' => trim($_REQUEST['snils']),
            'lastname' => trim($_REQUEST['lastname']),
            'firstname' => trim($_REQUEST['firstname']),
            'fathername' => trim($_REQUEST['fathername']),
            'sex' => $_REQUEST['sex'],
            'birthday' =>  $dt->format('Y-m-d'),
            'phone' => trim($_REQUEST['phone']),
            'email' => trim($_REQUEST['email']),
            'pedstage' => $_REQUEST['stage'],
            'checked' => 0,
            'region' => $_REQUEST['region'],
            'city' => trim($_REQUEST['city']),
            'distinctrm' => ($_REQUEST['region'] == 13 ? $_REQUEST['regionrm'] : null),
            'distinct' => ($_REQUEST['region'] != 13 ? $_REQUEST['distinct'] : null),
            'address' => trim($_REQUEST['address']),
            'cityrm' => (isset($_REQUEST['cityrm']) ? 1 : 0)
        );
        if (isset($_REQUEST['changepass']))
            $data['password'] = password_hash($_REQUEST['pass1'], PASSWORD_BCRYPT);

         $extfile = substr(strrchr($_FILES['sscan']['name'], '.'), 1);
         $uploadfile = uploaddir . 'snils_' . (int)$_REQUEST['id'].'.'.$extfile;
            if ($_FILES['sscan']['tmp_name'] != ''){
                if (move_uploaded_file($_FILES['sscan']['tmp_name'], $uploadfile)) {
                    $data['extfile'] = substr(strrchr($_FILES['sscan']['name'], '.'), 1);
                    Error::pdump('Скан СНИЛС загружен');
                    Error::pdump('и записан в базу');
                } else {
                    Error::pdump('Ошибка загрузки скана СНИЛС.');
                    $this->setError('Скан СНИЛС загрузить не удалось.');
                }
            }
        
        $new = $_REQUEST['id'] == '';
        if (!$new){
            $insertId = \QB::table('users')
                    ->where('id', $moder ? filter_var($_REQUEST['id'], FILTER_SANITIZE_NUMBER_INT) : $this->user->id)
                    ->update($data);
        } else {
            $insertId = \QB::table('users')->insert($data);
        }
        
        Error::pdump($insertId, 'Результат записи в базу');
        
        if(is_null($insertId)){
            $this->setError('Ошибка записи данных пользователя в базу!');
            return false;
        }
        
        if (!$moder) $this->addContent('Изменения сохранены');
        
        //$this->addHeader('<meta http-equiv="refresh" content="3; URL='.SITE.'/iom/?act=profile" />');
        $this->addRedirect(($moder ? $_SERVER['HTTP_REFERER'].'&referer='.$_REQUEST['referer'] : SITE.'/iom/?act=profile'), ($moder ? 0 : 5));
        return true;
    }
    
    /** ===================================================================================================================
     * Сохраняет данные паспорта пользователя
     * @return boolean
     */
    public function savePassport($moder = false) {
        if (!$this->isAuthorized()){
            $this->setError('Вы не авторизованы! Сначала <a href="/iom/?act=loginform">войдите под своей учетной записью</a>!');
            return false;
        }
        
        $dt = \DateTime::createFromFormat('d.m.Y', $_REQUEST['pdata']);
        
        $data = array(
            'citizenship' => $_REQUEST['citizenship'],
            'series' => trim($_REQUEST['pseries']),
            'number' => trim($_REQUEST['pnumber']),
            'datedoc' =>  $dt->format('Y-m-d'),
            'info' => trim($_REQUEST['pvidan'])
        );
        
        Error::pdump($data, 'Данные для БД');
        $new = $_REQUEST['id'] == '';
        
        $extfile = null;
        if ($_FILES['pscan']['tmp_name'] != ''){
            $extfile = substr(strrchr($_FILES['pscan']['name'], '.'), 1);
            
            $uploadfile = uploaddir . 'pass_' . (int)$_REQUEST['id'].'.'.$extfile;
            if (move_uploaded_file($_FILES['pscan']['tmp_name'], $uploadfile)) {
                    $data['extfile'] = $extfile;
                    Error::pdump('Скан паспорта загружен');
                } else {
                    Error::pdump('Ошибка загрузки скана паспорта.');
                    $this->setError('Скан паспорта загрузить не удалось.');
                }
        }
        
        if (!$new){
           $insertId = \QB::table('pass')
                    ->where('id', $_REQUEST['id'])
                    ->update($data);
        } else {
            //$insertId = \QB::table('pass')->insert($data);
            
            $insertId = \QB::transaction(function () {
                \QB::table('pass')->where('parent', $this->user->id)->update(array('used' => 0));
                $dt = \DateTime::createFromFormat('d.m.Y', $_REQUEST['pdata']);
                 $insertId = \QB::table('pass')->insert(array(
                                                'citizenship' => $_REQUEST['citizenship'],
                                                'series' => trim($_REQUEST['pseries']),
                                                'number' => trim($_REQUEST['pnumber']),
                                                'datedoc' =>  $dt->format('Y-m-d'),
                                                'info' => trim($_REQUEST['pvidan']),
                                                'parent' => $this->user->id,
                                                'used' => 1
                                            ));
                 Error::pdump($insertId, 'Результат записи в БД');
                 return $insertId;
            });
            if ($_FILES['pscan']['tmp_name'] != ''){
                rename ($uploadfile, uploaddir . 'pass_' . ($new ? $insertId : $_REQUEST['id']).'.'.$extfile);
            }
        }
        
        if(is_null($insertId)){
            $this->setError('Ошибка записи данных пользователя в базу!');
            return false;
        }
        
        if (!$moder) $this->addContent('Изменения сохранены');
        
        //$this->addHeader('<meta http-equiv="refresh" content="3; URL='.SITE.'/iom/?act=profile" />');
        $this->addRedirect(($moder ? $_SERVER['HTTP_REFERER'].'&referer='.$_REQUEST['referer'] : SITE.'/iom/?act=profile'), ($moder ? 0 : 5));
        return true;
    }
    
    /** ===================================================================================================================
     * Сохраняет данные о работе пользователя
     * @return boolean
     */
    public function saveWork($moder = false) {
        if (!$this->isAuthorized()){
            $this->setError('Вы не авторизованы! Сначала <a href="/iom/?act=loginform">войдите под своей учетной записью</a>!');
            return false;
        }
        
        $data = array(
            'organisation' => trim($_REQUEST['organisation']),
            'region' => trim($_REQUEST['wregion']),
            'distinctrm' => trim($_REQUEST['wregionrm']),
            'city' => trim($_REQUEST['wcity']),
            'profession' => trim($_REQUEST['dolgnost']),
            'stage' => trim($_REQUEST['wstage']),
            'gosslujba' =>  (isset($_REQUEST['gosslujba']) ? 1: 0),
            'phone' => trim($_REQUEST['wphone']),
            'waddress' => trim($_REQUEST['waddress']),
            'checked' => 0
        );
            
        $new = $_REQUEST['id'] == '';
        if (!$new){
            $insertId = \QB::table('work')->where('id', $_REQUEST['id'])->update($data);
        } else {
            $data['parent'] = $this->user->id;
            $insertId = \QB::table('work')->insert($data);
        }
        Error::pdump($insertId, 'Результат записи в БД');
        
        if(is_null($insertId)){
            $this->setError('Ошибка записи данных пользователя в базу!');
            return false;
        }
        
        if (!$moder) $this->addContent('Изменения сохранены');
        
        //$this->addHeader('<meta http-equiv="refresh" content="3; URL='.SITE.'/iom/?act=profile" />');
        $this->addRedirect(($moder ? $_SERVER['HTTP_REFERER'].'&referer='.$_REQUEST['referer'] : SITE.'/iom/?act=profile'), ($moder ? 0 : 5));
        return true;
    }

    /** ===================================================================================================
     * Блокирует\разблокирует курс для записи слушателей
     * 
     */
    public function lockCourse($id = null) {
        if (!$this->isAuthorized()){
            return false;
        }
        if ( !$this->isRule(RULE_MODERATE)){
            return false;
        }
        $query = \QB::table('course')->select('course.regclosed')->find($id);
        //Error::pdump($query);
        $data = array(
            'regclosed' => ($query->regclosed == 1 ? 0 : 1)
        );
        $insertId = \QB::table('course')->where('id', $id)->where('owner', $this->user->id)->update($data);
        $html = '<i class="fa-light fa-lock'.($query->regclosed == 1 ? '-open' : '').'" title="'.($query->regclosed == 1 ? 'За' : 'От').'крыть для записи курс"></i>';
        $this->addContent($html);
    }
    
    /** ===================================================================================================================
     * Сохраняет данные о дипломе пользователя
     * @return boolean
     */
    public function saveDiplom($moder = false) {
        if (!$this->isAuthorized()){
            $this->setError('Вы не авторизованы! Сначала <a href="/iom/?act=loginform">войдите под своей учетной записью</a>!');
            return false;
        }
                
        $dt = \DateTime::createFromFormat('d.m.Y', $_REQUEST['ddata']);
        
        $data = array(
            'edu_level' => $_REQUEST['edulevel'],
            'almamatter' => trim($_REQUEST['almamatter']),
            'series' => trim($_REQUEST['dseries']),
            'number' => trim($_REQUEST['dnumber']),
            'regnumber' => trim($_REQUEST['regnumber']),
            'datedoc' =>  $dt->format('Y-m-d'),
            'qualification' => trim($_REQUEST['qualification']),
            'stepen' => trim($_REQUEST['stepen']),
            'zvanie' => trim($_REQUEST['zvanie']),
            'f' => ($_REQUEST['dlastname'] == '' ? NULL : trim($_REQUEST['dlastname'])),
            'i' => ($_REQUEST['dfirstname'] == '' ? NULL : trim($_REQUEST['dfirstname'])),
            'o' => ($_REQUEST['dfathername'] == '' ? NULL : trim($_REQUEST['dfathername']))
            );
        $extfile = null;
        if ($_FILES['dscan']['tmp_name'] != ''){
            $extfile = substr(strrchr($_FILES['dscan']['name'], '.'), 1);
            
            $duploadfile = uploaddir . 'diplom_' . (int)$_REQUEST['id'].'.'.$extfile;
            if (move_uploaded_file($_FILES['dscan']['tmp_name'], $duploadfile)) {
                    $data['dextfile'] = $extfile;
                    Error::pdump('Скан диплома загружен');
                } else {
                    Error::pdump('Ошибка загрузки скана диплома.');
                    $this->setError('Скан диплома загрузить не удалось.');
                }
        }
        
        $extfile = null;
        if ($_FILES['fscan']['tmp_name'] != ''){
            $extfile = substr(strrchr($_FILES['fscan']['name'], '.'), 1);
            
            $fuploadfile = uploaddir . 'fio_' . (int)$_REQUEST['id'].'.'.$extfile;
            if (move_uploaded_file($_FILES['fscan']['tmp_name'], $fuploadfile)) {
                    Error::pdump('Скан документа о смене ФИО загружен');
                } else {
                    Error::pdump('Ошибка загрузки скана документа о смене ФИО.');
                    $this->setError('Скан документа о смене ФИО загрузить не удалось.');
                }
        }
        $data['fextfile'] = $extfile;
        
        $new = $_REQUEST['id'] == '';
        if (!$new){
            $insertId = \QB::table('diplom')->where('id', $_REQUEST['id'])->update($data);
        } else {
            $data['parent'] = $this->user->id;
            $insertId = \QB::table('diplom')->insert($data);
            if ($_FILES['dscan']['tmp_name'] != ''){
                rename ($duploadfile, uploaddir . 'diplom_' . $insertId.'.'.$extfile);
            }
            if ($_FILES['fscan']['tmp_name'] != ''){
                rename ($fuploadfile, uploaddir . 'fio_' . $insertId.'.'.$extfile);
            }
        } 
        
         Error::pdump($new, 'Новый диплом или правим старый');
        Error::pdump($insertId, 'Результат записи в БД');
        
        if(is_null($insertId)){
            $this->setError('Ошибка записи данных пользователя в базу!');
            return false;
        }
        
        if (!$moder) $this->addContent('Изменения сохранены');
        
        //$this->addHeader('<meta http-equiv="refresh" content="3; URL='.SITE.'/iom/?act=profile" />');
        $this->addRedirect(($moder ? $_SERVER['HTTP_REFERER'].'&referer='.$_REQUEST['referer'] : SITE.'/iom/?act=profile'), ($moder ? 0 : 5));
        return true;
    }
    


    /** ===========================================================================================================================
     * Функция авторизации пользователя
     * @param Int $id - ID пользователя
     * @param String $pass - пароль
     * @return boolean - true если авторизация успешна
     */
    public function auth($id = null, $pass = null) {
        if (!is_null($id) && !is_null($pass)){
            $login = 'id';
        } elseif  (isset($_REQUEST['ep'])){
            $login = 'email';
        } else {
            $login = 'phone';
        }
        $query = \QB::table('users')->select(array(
                                            'users.id', 'users.snils','users.extfile', 'users.group', 'users.rules', 'users.added', 'users.closed', 
                                            'users.firstname', 'users.lastname', 'users.fathername', 'users.sex', 
                                            \QB::raw("DATE_FORMAT(".\OnlineRecord\config['prefix']."users.birthday, '%d.%m.%Y') AS birthday"), 
                                            'users.phone', 'users.email', 'users.pedstage', 'users.password', 'users.region', 'users.city', 'users.distinct','users.address',
                                            'users.distinctrm', 'users.cityrm', 'users.checked', 'users.activated'))
                            ->where($login, '=', (isset($_REQUEST['eort']) ? $_REQUEST['eort'] : $id))->where('activated', '=', 1)->where('banned', '=', 0);
        
        $result = $query->first();
        //Error::pdump('auth');
        //Error::pdump($result);
        //Error::pdump($query->getQuery()->getRawSql());
        if (is_null($result)){
            $this->setError('Неверные данные для входа или такого пользователя не существует! Или, возможно, вы не подтвердили свою учетную запись?<br>'
                .'Не пришло письмо активации учетной записи? Мы можем <a class="button open_modal" href="#modal1">выслать его снова</a>.');
            Error::pdump('Авторизация не прошла');
            $html = '<form action="/" method="post" enctype="multipart/form-data"><input type="hidden" name="act" value="resendactivationmail"><label>Введите свой e-mail:<br><input type="text" name="email" style="width: 98%;"><br /><input type="submit" value="Отправить"></label></form>';
            $this->addModalContent($html);
            return false;
        }
        
        /*if (!is_null($result->extfile))
            $this->files[] = 'snils_'.$result->id.'.'.$result->extfile;*/
        
        if (!is_null($id) && !is_null($pass)){
            if ($pass == $result->password){
                $this->authorized = true;
                $this->user = $result;
                Error::pdump('Успешная авторизация из куки', 'Auth');
                return true;
            }
        }
        if (password_verify($_REQUEST['pass'], $result->password)) {
            $this->authorized = true;
            $this->user = $result;
            $c = ['user' => $this->user->id,
                  'pass' => $this->user->password  ];

            setcookie('onlineRecord', $this->encrypt(serialize($c)), time()+7776000, '/', $_SERVER['SERVER_NAME']);
            Error::pdump('Успешная авторизация');

            //$this->addHeader('<meta http-equiv="refresh" content="0; URL='.SITE.($_REQUEST['courseid'] != '' ? '/iom/?act=showmore&id='.$_REQUEST['courseid'] : '').'" />');
            
            $this->addRedirect(SITE.(isset($_REQUEST['cat']) ? '/iom' : ''), 0);
            return true;
        } else {
            $this->setError('Неверные данные для входа или такого пользователя не существует! Или, возможно, вы не подтвердили свою учетную запись?<br>'
                .'Не пришло письмо активации учетной записи? Мы можем <a class="button open_modal" href="#modal1">выслать его снова</a>.');
            Error::pdump('Авторизация не прошла');
            $html = '<form action="/" method="post" enctype="multipart/form-data"><input type="hidden" name="act" value="resendactivationmail"><label>Введите свой e-mail:<br><input type="text" name="email" style="width: 98%;"><br /><input type="submit" value="Отправить"></label></form>';
            $this->addModalContent($html);
            return false;
        }
    }

    
    /** ========================================================================================================================================
     * Функция подтверждения учетной записи через почту
     * @param type $actCode - код активации
     */
    public function activation($actCode) {
        $code = urldecode($this->decrypt($actCode));
        $data = array(
                    'activated' => 1
                );
        $query = \QB::table('users')->where('email', '=', $code)->where('banned', '=', 0)->update($data);
        $query = \QB::table('users')->where('email', '=', $code)->where('banned', '=', 0)->where('activated', '=', 1);
        $count = $query->count();
        //Error::pdump('Активация: '.$code.' - '.$query);
        if ($count == 1)
            $this->addContent('Учетная запись активирована! Сейчас вы будете перемещены на форму входа.');
        else
            $this->addContent('Некорректный код активации! Сейчас вы будете перемещены на форму входа.');
        //$this->addHeader('<meta http-equiv="refresh" content="5; URL='.SITE.'" /iom/?act=loginform>');
        $this->addRedirect(SITE.'/iom/?act=loginform', 5);
    }

    
    /** ========================================================================================================================================
     * Функция, высылающая письмо восстановления пароля
     * @return boolean
     */
    public function lostPass() {
        $err = '';
        if (!validateSnils($_REQUEST['snils'], $err)){
            $this->setError($err);
            return false;
        }
        
        $query = \QB::table('users')
                    ->where('snils', '=', trim($_REQUEST['snils']))
                    ->where('phone', '=', trim($_REQUEST['phone']))
                    ->where('email', '=', trim($_REQUEST['email']));
        $result = $query->first();
        $count = $query->count();
        
        if ($count == 0){
            $this->setError('Пользователь с такими данными (СНИЛС, телефон или e-mail) не существует! Вы можете <a href="/iom/?act=regform">создать новую учетную запись</a> на сайте.');
            return false;
        } else {
            $body    = 'Здравствуйте.<br />Мы получили запрос на восстановление пароля доступа к записи на курсы. '
                     . 'Если вы не посылали такой запрос, то просто проигнорируйте это письмо.<br />'.
                       'Если же это вы оставили запрос, то для смены пароля <a href="'.SITE.'/iom/?act=resetpassform&id='
                      .urlencode($this->encrypt($result->snils)).'">перейдите по ссылке.</a>'
                    .'<p></p><p>Это письмо сгенерировано автоматически, отвечать на него не нужно - письмо ни до кого не дойдет!</p>';
            $this->sendMail($_REQUEST['email'], 
                            'Восстановление пароля на сайте Педагог13.ру', 
                            $body, 
                            'Проверьте свой почтовый ящик. На него должно прийти письмо со ссылкой на сброс пароля. '
                            . 'Это может занять некоторое время. Если письмо не пришло, то возможно оно попало в спам.');
        }
    }
    
    
    /** ========================================================================================================================================
     * Функция выводящая форму восстановления пароля
     * @return boolean
     */
    public function resetPassForm() {
        $query = \QB::table('users')->where('snils', '=', urldecode($this->decrypt($_REQUEST['id'])));
        $result = $query->first();
        $count = $query->count();
        
        if ($count == 0){
            $this->setError('Пользователь не найден.');
            return false;
        } else {
            $this->addContent(file_get_contents(dirname(__FILE__).'/resetpassForm.html'));
            $this->addJQCode(file_get_contents(dirname(__FILE__).'/resetpassForm.jqs'));
            $this->addRtemplate('{%ID%}', $_REQUEST['id']);
            $this->addRtemplate('{%USERID%}', $_REQUEST['id']);
        }
    }
    
    
    /** ========================================================================================================================================
     * Функция установки нового пароля после сброса пароля
     * @return boolean
     */
    public function resetPass() {
        
        if ($_REQUEST['pass1'] != $_REQUEST['pass2']){
            $this->setError('Пароли не совпадают!');
            return false;
        }
        $query = \QB::table('users')->where('snils', '=', urldecode($this->decrypt($_REQUEST['id'])));
        $result = $query->first();
        $count = $query->count();
        
        if ($count == 0){
            $this->setError('Пользователь не найден.');
            return false;
        } else {

            $data = array(
                'password'        => password_hash($_REQUEST['pass1'], PASSWORD_BCRYPT)
            );
            $query = \QB::table('users')->where('snils', '=', urldecode($this->decrypt($_REQUEST['id'])))->update($data);
        }
    }
    
    
    /** ========================================================================================================================================
     * Функция возвращает список регионов в теге <select>
     * @param Int $sel - выбранный пункт по умолчанию
     * @return string
     */
    public function getRegions($sel = null, $work = false) {
        $query = \QB::table('regions')->select('*')->orderBy('region');
        $result = $query->get();
        Error::pdump($work, 'Регионы');
        //Error::pdump($result);
        
        $html = '<select name="'.($work ? 'w' : '').'region" id="'.($work ? 'w' : '').'region" class="js-select2">';
        
        foreach ($result as $val) {
            if (is_null($sel)){
                $html .= '<option value="'.$val->id.'" '.($val->id == 13 ? ' selected="selected"' : '').'>'.$val->region.'</option>';
            } else {
                $html .= '<option value="'.$val->id.'" '.($val->id == $sel ? ' selected="selected"' : '').'>'.$val->region.'</option>';
            }
        }
        $html .= '</select>';
        return $html;
    }

    /** ========================================================================================================================================
     * Функция возвращает список категорий слушателей в теге <select>
     * @param Int $sel - выбранный пункт по умолчанию
     * @return string
     */
    public function getPredmets($sel = null) {
        $query = \QB::table('predmet')->select('*')->orderBy('predmet');
        $result = $query->get();
        $html = '<select class="js-select2" name="predmet" id="predmet">';
        
        foreach ($result as $val) {
            if (is_null($sel)){
                $html .= '<option value="'.$val->id.'" >'.$val->predmet.'</option>';
            } else {
                $html .= '<option value="'.$val->id.'" '.($val->id == $sel ? ' selected="selected"' : '').'>'.$val->predmet.'</option>';
            }
        }
        $html .= '</select>';
        return $html;
    }

    /** ========================================================================================================================================
     * Функция возвращает список статусов заявок в теге <select>
     * @param Int $sel - выбранный пункт по умолчанию
     * @return string
     */
    public function getAppType($sel = null) {
        $query = \QB::table('appstatus')->select('*')->orderBy('id');
        $result = $query->get();
        $html = '<select name="apptype" id="apptype"><option value="0" >Все заявки</option>';
        
        foreach ($result as $val) {
            if (is_null($sel)){
                $html .= '<option value="'.$val->id.'" >'.$val->status.'</option>';
            } else {
                $html .= '<option value="'.$val->id.'" '.($val->id == $sel ? ' selected="selected"' : '').'>'.$val->status.'</option>';
            }
        }
        $html .= '</select>';
        return $html;
    }
    
    /** ========================================================================================================================================
     * Функция возвращает список режимов отрыва от производства в теге <select>
     * @param Int $sel - выбранный пункт по умолчанию
     * @return string
     */
    public function getModes($sel = null) {
        $query = \QB::table('cmode')->select('*')->orderBy('id');
        $result = $query->get();
        $html = '<select name="mode" id="mode">';
        
        foreach ($result as $val) {
            if (is_null($sel)){
                $html .= '<option value="'.$val->id.'" >'.$val->mode.'</option>';
            } else {
                $html .= '<option value="'.$val->id.'" '.($val->id == $sel ? ' selected="selected"' : '').'>'.$val->mode.'</option>';
            }
        }
        $html .= '</select>';
        return $html;
    }
    
    /** ========================================================================================================================================
     * Функция возвращает список форм обучения в теге <select>
     * @param Int $sel - выбранный пункт по умолчанию
     * @return string
     */
    public function getForms($sel = null) {
        $query = \QB::table('form')->select('*')->orderBy('id');
        $result = $query->get();
        $html = '<select name="form" id="form">';
        
        foreach ($result as $val) {
            if (is_null($sel)){
                $html .= '<option value="'.$val->id.'" >'.$val->form.'</option>';
            } else {
                $html .= '<option value="'.$val->id.'" '.($val->id == $sel ? ' selected="selected"' : '').'>'.$val->form.'</option>';
            }
        }
        $html .= '</select>';
        return $html;
    }
    
    /** ========================================================================================================================================
     * Функция возвращает список уровней образования в теге <select>
     * @param Int $sel - выбранный пункт по умолчанию
     * @return string
     */
    public function getEdulevel($sel = null) {
        $query = \QB::table('education_level')->select('*')->orderBy('id');
        $result = $query->get();
        //Error::pdump('Уровни образования');
        //Error::pdump($result);
        
        $html = '<select name="edulevel" id="edulevel">';
        
        foreach ($result as $val) {
            if (is_null($sel)){
                $html .= '<option value="'.$val->id.'">'.$val->level.'</option>';
            } else {
                $html .= '<option value="'.$val->id.'" '.($val->id == $sel ? ' selected="selected"' : '').'>'.$val->level.'</option>';
            }
        }
        $html .= '</select>';
        return $html;
    }
    
    /** ========================================================================================================================================
     * Функция возвращает список стран в теге <select>
     * @param Int $sel - выбранный пункт по умолчанию
     * @return string
     */
    public function getCountries($sel = null) {
        $query = \QB::table('citizenship')->select('*')->orderBy('citizenship');
        $result = $query->get();
        //Error::pdump('Страны: ');
        //Error::pdump($result);
        $html = '<select name="citizenship" id="citizenship">';
        foreach ($result as $val) {
            if (is_null($sel)){
                $html .= '<option value="'.$val->id.'" '.($val->id == 1 ? ' selected="selected"' : '').' >'.$val->citizenship.'</option>';
            } else {
                $html .= '<option value="'.$val->id.'" '.($val->id == $sel ? ' selected="selected"' : '').' >'.$val->citizenship.'</option>';
            }
        }
        $html .= '</select>';
        return $html;
    }
    
    /** ========================================================================================================================================
     * Функция возвращает список полов в теге <select>
     * @param Int $sel - выбранный пункт по умолчанию
     * @return string
     */
    public function getSex($sel = null) {
        $query = \QB::table('sex')->select('*')->orderBy('id');
        $result = $query->get();
       // Error::pdump('Пол: ');
       // Error::pdump($result);
        
        $html = '<select name="sex"  id="sex">';
        foreach ($result as $val) {
            if (is_null($sel))
                $html .= '<option value="'.$val->id.'" >'.$val->sex.'</option>';
            else
                $html .= '<option value="'.$val->id.'" '.($val->id == $sel ? ' selected="selected"' : '').'>'.$val->sex.'</option>';
        }
        $html .= '</select>';
        return $html;
    }
    
    /** ========================================================================================================================================
     * Функция возвращает список районов РМ в теге <select>
     * @param Int $sel - выбранный пункт по умолчанию
     * @return string
     */
    public function getDistinct($sel = null, $work = false) {
        $query = \QB::table('distinct')->select('*')->orderBy('distinct');
        $result = $query->get();
        Error::pdump($result,'Районы РМ: ');
        $html = '<select name="'.($work ? 'w' : '').'regionrm" id="'.($work ? 'w' : '').'regionrm" class="select2">';
        //$html .= '<option value="0" selected="selected">=== Выберите район ===</option>';
        foreach ($result as $val) {
            /*if (is_null($sel))
                $html .= '<option value="'.$val->id.'" >'.$val->distinct.'</option>';
            else*/
                $html .= '<option value="'.$val->id.'" '.($val->id == $sel ? ' selected="selected"' : '').' '.(!is_null($val->city) ? 'data-city="'.$val->city.'"' : '').'>'.$val->distinct.'</option>';
        }
        $html .= '</select>';
        return $html;
    }


    //========================================================================================================================================
    public function setAppState($appid, $state, $course = 0) {
        if (!$this->isAuthorized()) return false;
        Error::pdump('id-'.$appid.' st-'.$state, 'SetAppState');
        $data = array(
            'status' => $state,
            //'group' => $result->id,
            'status_changed' => 1
            );
        if ($state == APP_STATUS_ACCEPTED){
            $query = \QB::table('coursegroup')->where('course', $course)->orderBy('id');
            $result = $query->first();
            $data['group'] = $result->id;
        }
        if ($state == APP_STATUS_REJECTED){
            $data['group'] = 0;
        }
        
        $insertId = \QB::table('applications')->where('id', $appid)->update($data);
    }
    
    //========================================================================================================================================
    public function isRule($rule) {
        if (!$this->isAuthorized()) return false;
        return ($rule & $this->user->rulesR) == $rule;
    }
    
    
    /** ========================================================================================================================================
     * Возвращает готовность всех документов
     * @param type $rtype - тип документов [ READY_ALL | READY_PASSPORT | READY_DIPLOM | READY_WORK ]
     * @return boolean
     */
    public function isReady($rtype) {
        if (!$this->isAuthorized()) 
            return false;
        switch ($rtype) {
         case READY_ALL:
                return $this->added['passport'] && $this->added['diplom'] && $this->added['work'];
             break;
         case READY_PASSPORT:
                return $this->added['passport'];
             break;
         case READY_DIPLOM:
                return $this->added['diplom'];
             break;
         case READY_WORK:
                return $this->added['work'];
             break;
        }
    }
    
    /** ========================================================================================================================================
     * Читает из базы данные пользователя (личные данные, паспорта, дипломы, места работ) и заполняет объект LK
     * @return boolean
     */
    public function getUserInfo() {
        if (!$this->isAuthorized()){
            //$this->setError('Вы не авторизованы! Сначала <a href="/iom/?act=loginform">войдите под своей учетной записью</a>!');
            return false;
        }

        $query = \QB::table('users')->select(array(
                                        'users.sex','users.region', 'users.distinctrm', 'sex.sex', 'regions.region', 'distinct.distinct',
                                        'ugroup.group', 'ugroup.rules', 'users.extfile', 'users.id'
                                    ))
                ->leftJoin('sex', 'sex.id', '=', 'users.sex')
                ->leftJoin('regions', 'regions.id', '=', 'users.region')
                ->leftJoin('distinct', 'distinct.id', '=', 'users.distinctrm')
                ->leftJoin('ugroup', 'ugroup.id', '=', 'users.group')
                ->where('users.id', '=', $this->user->id);

        //Error::pdump($query->getQuery()->getRawSql());
        $result = $query->first();
        //Error::pdump($result, 'Юзер');
        if (!is_null($result->extfile))
            $this->files[] = 'snils_'.$result->id.'.'.$result->extfile;
        
        $this->user->sexR = $result->sex;
        $this->user->regionR = $result->region;
        $this->user->distinctrmR = $result->distinct;
        $this->user->groupR = $result->group;
        $this->user->rulesR = $result->rules | $this->user->rules;
        
        $query = \QB::table('messages')->where('messages.owner', $this->user->id)->orderBy('messages.sended', 'DESC');
        $result = $query->get();
        $this->messages = $result;
        if (!is_null($result)){
            $this->message_count = count($result);
            foreach ($result as $v) {
                if ($v->viewed == 0)
                    $this->new_message_count++;
            }
        }
        
        $query = \QB::table('applications')->where('applications.student', $this->user->id);
        $this->applicationCount = $query->count();
        $query = \QB::table('applications')->where('applications.student', $this->user->id)->where('applications.status_changed', 1);
        $this->appChangedCount = $query->count();
        
        if ($this->isRule(RULE_MODERATE)){
            $query = \QB::table('applications')->leftJoin('course', 'course.id', '=', 'applications.course')
                    ->where('course.owner', $this->user->id)->where('applications.status', 1);
            $this->appModerCount = $query->count();
        }
        
        $query = \QB::table('pass')->select(array(
                                            'pass.id', \QB::raw(\OnlineRecord\config['prefix'].'pass.citizenship as citizen'), 'pass.series', 'pass.number', 
                                            \QB::raw("DATE_FORMAT(".\OnlineRecord\config['prefix']."pass.datedoc,'%d.%m.%Y') as datedoc"), 'pass.info', 
                                            'pass.extfile', 'pass.parent', 'pass.checked', 'citizenship.citizenship'))
                        ->leftJoin('citizenship', 'citizenship.id', '=', 'pass.citizenship')
                        ->where('pass.parent', '=', $this->user->id);
        $result = $query->get();
        
        if ($query->count() != 0)
            $this->added['passport'] = true;
            
        $this->pass = $result;
        foreach ($result as $v) {
            if (!is_null($v->extfile))
            $this->files[] = 'pass_'.$v->id.'.'.$v->extfile;
        }
        //Error::pdump($result);

        $query = \QB::table('diplom')->select(array(
                                        'diplom.id', 'diplom.edu_level', 'diplom.almamatter', 'education_level.level', 'diplom.series', 'diplom.number', 'diplom.regnumber', 
                                        \QB::raw("DATE_FORMAT(".\OnlineRecord\config['prefix']."diplom.datedoc,'%d.%m.%Y') as datedoc"), 'diplom.qualification', 
                                        'diplom.stepen', 'diplom.zvanie', 'diplom.f', 'diplom.i', 'diplom.o', 
                                        'diplom.parent', 'diplom.dextfile', 'diplom.fextfile', 'diplom.checked'))
                        ->leftJoin('education_level', 'education_level.id', '=', 'diplom.edu_level')
                        ->where('parent', '=', $this->user->id);
        $result = $query->get();
        
        if ($query->count() != 0)
            $this->added['diplom'] = true;
        
        $this->diplom = $result;
        //Error::pdump($result, 'Дипломы');
        foreach ($result as $v) {
            if (!is_null($v->dextfile))
                $this->files[] = 'diplom_'.$v->id.'.'.$v->dextfile;
            if (!is_null($v->fextfile))
                $this->files[] = 'fio_'.$v->id.'.'.$v->fextfile;

        }
        $query = \QB::table('work')->select(
                            \QB::raw(\OnlineRecord\config['prefix'].'work.id as workid'), 'work.parent', 'work.organisation', 'work.waddress', 'work.profession', 'work.stage', 
                            'work.region', \QB::raw(\OnlineRecord\config['prefix'].'regions.region as regionR'), 'work.distinctrm', \QB::raw(\OnlineRecord\config['prefix'].'distinct.distinct as distinctR'),
                            'work.city', 'work.gosslujba', 'work.phone','work.checked'
                        )
                ->leftJoin('regions', 'regions.id', '=', 'work.region')
                ->leftJoin('distinct', 'distinct.id', '=', 'work.distinctrm')
                ->where('parent', '=', $this->user->id);
        $result = $query->get();

        if ($query->count() != 0)
            $this->added['work'] = true;
        
        $this->job = $result;
        
        /*foreach ($result as $val) {
            //$val;
        }*/
        
    }

    /** ========================================================================
     *  удаляет группу
     * @param int $id  - id группы
     * @return boolean
     */
    public function deleteGroup(int $id) {
        if (!$this->isAuthorized()){
            $this->setError('Вы не авторизованы! Сначала <a href="/iom/?act=loginform">войдите под своей учетной записью</a>!');
            return false;
        }
        if ( !$this->isRule(RULE_MODERATE) ){
            $this->setError('Вы не имеете доступа к этому функционалу! Обратитесь к администратору!');
            return false;
        }
        $insertId = \QB::table('coursegroup')->where('coursegroup.id', $id)->delete();
        if (is_null($insertId)){
            $this->setError('Ошибка удаления группы!');
            return false;
        }
        $this->addRedirect($_SERVER['HTTP_REFERER'], 0);
        return true;
    }
    
    /**=========================================================================
     * Возвращает json c данными приказа о зачислении. Если приказ не печатался, возвращается значение из таблицы настроек, иначе читается номер из таблицы группы
     * @param Integer $group - id группы
     */
    public function getOrderAddInfo(String $group) {
        $res = array();
        $query = \QB::table('coursegroup')->select('id', 'orderAddNumber', \QB::raw("DATE_FORMAT(orderAddDate,'%d.%m.%Y') as orderAddDate"))->find($group); 
        Error::pdump($query);
        if (is_null($query->orderAddNumber)){
            $res['pNumber'] = Settings::getSetting('orderAddNumber');
            //$res['regnumber'] = $query->regnumber;
            $res['date'] = date('d.m.Y');
            $res['saved'] = false;
        } else {
            $res['pNumber'] = $query->orderAddNumber;
            //$res['regnumber'] = $query->regnumber;
            $res['date'] = $query->orderAddDate;
            //$res['regnumber'] = $query->regnumber;
            $res['saved'] = true;
        }
        $this->addContent(json_encode($res));
    }
    
    /**=========================================================================
     * Возвращает json c данными приказа об отчислении. Если приказ не печатался, возвращается значение из таблицы настроек, иначе читается номер из таблицы заявок
     * @param Integer $group - id группы
     */
    public function getOrderExpInfo(String $group) {
        $res = array();
        $query = \QB::table('applications')->select('id')->where('group', $group)->where('status', APP_STATUS_EXPULSION); 
        $count = $query->count();
        
        if ($count == 0) {
            $res['error'] = 'В группе нет отчисленных!';
        } else {
            $res['error'] = '';
        }
        
        $query = \QB::table('coursegroup')->select('id', 'orderExpNumber', \QB::raw("DATE_FORMAT(orderExpDate,'%d.%m.%Y') as orderExpDate"))->find($group); 
        Error::pdump($query);
        if (is_null($query->orderExpNumber)){
            $res['pNumber'] = Settings::getSetting('orderAddNumber');
            //$res['regnumber'] = $query->regnumber;
            $res['date'] = date('d.m.Y');
            $res['saved'] = false;
        } else {
            $res['pNumber'] = $query->orderExpNumber;
            //$res['regnumber'] = $query->regnumber;
            $res['date'] = $query->orderExpDate;
            //$res['regnumber'] = $query->regnumber;
            $res['saved'] = true;
        }
        $this->addContent(json_encode($res));
    }


    /** ========================================================================================================================================
     * Заменяет в LK->Ghtml все шаблоны на значения
     * @param type $arr
     */
    public function parseHtml($arr) {
        foreach ($arr as $k => $v) {
            if (!is_null($v))
                $this->Ghtml = str_replace($k, $v, $this->Ghtml);
            else
                $this->Ghtml = str_replace($k, '', $this->Ghtml);
        }
    }
    
    //========================================================================================================================================
    public function getHtml() {
        if (is_null($this->Ghtml)){
            
            //Error::pdump((isset($_COOKIE['DEBUG']) ? $this->decrypt($_COOKIE['DEBUG']) : 'Нихьт'), 'Coock');
            
            // Если пришел запрос не AJAX
            if (!AJAX) {
                $this->Ghtml = file_get_contents(dirname(__FILE__).'/iom.html');

                //Error::pdump($this->Ghtml);
                if ($this->isAuthorized())
                    $login = '<a href="/iom/?act=logout" class="show-tooltip" title="Выйти из личного кабинета"><i class="fa-light fa-right-from-bracket"></i> Выйти</a>';
                else
                    $login = '<a href="/iom/?act=loginform" class="show-tooltip" title="Войти в личный кабинет"><i class="fa-light fa-right-to-bracket"></i> Войти</a>';
                $this->addRtemplate('{%LOGIN%}', $login);

                $this->addRtemplate('{%ADMIN%}', $this->isRule(RULE_ADMIN) ? '<li><a href="/iom/?act=admin" class="show-tooltip" title="Админ-панель"><i class="fa-light fa-gear"></i> Админка</a></li>' : '');
                $this->addRtemplate('{%DOCS%}', $this->isRule(RULE_DOCS) ? '<li><a href="/iom/?act=docs" class="show-tooltip" title="Документооборот"><i class="fa-light fa-file-certificate"></i> Документооборот</a></li>' : '');
                //$this->addRtemplate('{%STATISTICS%}', $this->isRule(RULE_CATALOGUE) || $this->isRule(RULE_REPORTS) ? '<li><a href="/iom/?act=statistics" class="show-tooltip" title="Статистика">Статистика</a></li>' : '');
                //$this->addRtemplate('{%MODERATE%}', $this->isRule(RULE_MODERATE) ? '<li><a href="/iom/?act=cattree" class="show-tooltip" title="Правка категорий/курсов">Управление курсами</a></li>' : '');
                $this->addRtemplate('{%REPORTS%}', $this->isRule(RULE_REPORTS) ? '<li><a href="/iom/?act=reports" class="show-tooltip" title="Отчеты и статистика"><i class="fa-light fa-file-chart-column"></i> Отчеты</a></li>' : '');
                $this->addRtemplate('{%PFRO%}', $this->isRule(RULE_REPORTS) ? '<li><a href="/iom/?act=pfro" class="show-tooltip" title="Базы ПФРО"><i class="fa-light fa-check-double"></i> ПФРО</a></li>' : '');
                $this->addRtemplate('{%PROFILE%}', $this->isRule(RULE_VIEW) ? '<li><a href="/iom/?act=profile" class="show-tooltip" title="Открыть профиль пользователя"><i class="fa-light fa-id-badge"></i> Мой профиль </a>'
                                .(($this->new_message_count > 0 || $this->appChangedCount > 0 || $this->appModerCount > 0) ?'<span id="messcount">'.($this->new_message_count + $this->appChangedCount + $this->appModerCount).'</span>' : '').'</li>' : '');
            
            // Если пришел AJAX запрос    
            } else { 
                if ($this->codeAdded) {
                    $this->Ghtml = '<script>'."\n"
                            .'{%JSCODE%}'."\n"
                            . '$(document).ready(function(){'."\n"
                            . '    {%JQCODE%} '."\n"
                            . '});'."\n"
                            . '</script>'."\n";
                }
                $this->Ghtml .= '{%CONTENT%}';
            }
        }
                
        $this->parseHtml($this->templates);
        return $this->Ghtml;
    }
    
    //========================================================================================================================================

    //===========================================================================================================================
    //====================================================================================================
    //========================================================================================================================================

    //========================================================================================================================================
    public function getReports() {
        if (!$this->isAuthorized()){
            $this->setError('Вы не авторизованы! Сначала <a href="/iom/?act=loginform">войдите под своей учетной записью</a>!');
            return false;
        }
        //Error::pdump('$cat='.$cat);
        //SHOW TABLE STATUS WHERE Name='table_name';
        $query = \QB::query("SHOW TABLE STATUS WHERE Name LIKE '".\OnlineRecord\config['prefix']."%'");

        //Error::pdump($query->getQuery()->getRawSql());
        $result = $query->get();
        Error::pdump($result, 'Коммент таблицы');
        $this->addContent('<h6>Список таблиц</h6>');
        foreach ($result as $v) {
            $this->addContent($v->Comment.'<br />');
        }
        $this->setTitle('Отчеты');
    }
    
    //========================================================================================================================================
    public function showAllMessages() {
        if (!$this->isAuthorized()){
            $this->setError('Вы не авторизованы! Сначала <a href="/iom/?act=loginform">войдите под своей учетной записью</a>!');
            return false;
        }
        $this->setTitle('Сообщения');
        $html = '';
        /*$query = \QB::table('messages')->select('*')->where('messages.owner', $this->user->id)->orderBy('sended');
        $result = $query->get();
        Error::pdump($result, 'Messages');*/
        foreach ($this->messages as $v) {
            $html .= '<div class="formrow shadow round5border dgraybg margin5updown message-block ">'
                    . '<p class="message-title '.($v->viewed == 0 ? 'noviewed' : 'viewed').'" data-id="'.$v->id.'" data-viewed="'.($v->viewed == 0 ? 'false' : 'true').'">'.$v->title.'</p>'
                    . '<div class="message-body">'.$v->message
                        .'<div class="message-link">'.(is_null($v->link) ? '' : '<a href="'.SITE.$v->link.'">'.SITE.$v->link.'</a>').'</div>'
                    . '</div>'
                    .'</div>';
        }
        
        $js = '$(".message-title").on("click", function(){ '
                                . '$(".message-body").hide(); '
                                . '$(this).next(".message-body").toggle(); '
                                . '$(this).removeClass("noviewed").addClass("viewed");'
                                . 'if ($(this).data("viewed") == false) {'
                                    . '$.post("/", { act: "mviewed",  id: $(this).data("id") } );'
                                . '} '
                                . '$(this).data("viewed", true);'
                                . 'var n = Number($("#messcount").text());'
                                . '$("#messcount").text(String(n-1));'
                . '});';
        $this->addContent($html);
        $this->addJQCode($js);
    }
    
    //========================================================================================================================================
    public function setMessageViewed($id, $set = 1) {
        if (!$this->isAuthorized()){
            $this->setError('Вы не авторизованы! Сначала <a href="/iom/?act=loginform">войдите под своей учетной записью</a>!');
            return false;
        }
        $data = array(
            'viewed' => $set
        );
            
        
            $insertId = \QB::table('messages')->where('id', $id)->update($data);
    }
    
    //========================================================================================================================================
    public function checkData($id, $type) {
        if (!$this->isAuthorized()){
            $this->setError('Вы не авторизованы! Сначала <a href="/iom/?act=loginform">войдите под своей учетной записью</a>!');
            return false;
        }
        $data = array(
            'checked' => 1
        );
        $insertId = \QB::table($type)->where('id', $id)->update($data);
        $this->addRedirect($_REQUEST['referer'], 0);
    }
    
    //========================================================================================================================================
    //function addCourse
    
    //========================================================================================================================================
    //function saveCourse
    
    //========================================================================================================================================
    public function addNewPredmet($name) {
        //Error::pdump('добавляем категорию '.$name);
         if ($name === 0) return false;
        if (!$this->isAuthorized()){
            $this->setError('Вы не авторизованы! Сначала <a href="/iom/?act=loginform">войдите под своей учетной записью</a>!');
            return false;
        }
        //Проверяем права
        if ( !$this->isRule(RULE_CATEGORY) ){
            $this->setError('Вы не имеете доступа к этому функционалу! Обратитесь к администратору!');
            return false;
        }
        $data = array(
            'predmet' => $name
        );
        $insertId = \QB::table('predmet')->insert($data);
        if(is_null($insertId)){
            $this->addContent('Ошибка записи категории в базу!');
            return false;
        }
        $this->addContent($this->getPredmets($insertId));
        $this->addContent('<script>$(\'.js-select2\').select2({	placeholder: "Выберите категорию слушателей", language: "ru"});</script>');
    }//function addNewPredmet
    
    //========================================================================================================================================
    public function deleteCategory($id) {
        if ($id == 0) return false;
        if (!$this->isAuthorized()){
            $this->setError('Вы не авторизованы! Сначала <a href="/iom/?act=loginform">войдите под своей учетной записью</a>!');
            return false;
        }
        //Проверяем права
        if ( !$this->isRule(RULE_CATEGORY) ){
            $this->setError('Вы не имеете доступа к этому функционалу! Обратитесь к администратору!');
            return false;
        }
        
//FIXME: обработать удаление категорий и курсов внутри удаляемой категории =====================================
        $insertId = \QB::table('ccategory')->where('id', $_REQUEST['id'])->delete();
        //Error::pdump($insertId, 'Результат записи в БД');

        if(is_null($insertId)){
            $this->setError('Ошибка удаления категории!');
        } else
            $this->addRedirect(SITE.'/iom/?act=cattree', 0);
    }//function deleteCategory
    
    //========================================================================================================================================
    //function renameCategory

    //========================================================================================================================================
    //function saveCategory
    
     //========================================================================================================================================
    //function catTree()
    
    
    //========================================================================================================================================

    //========================================================================================================================================


    //========================================================================================================================================

    //========================================================================================================================================
    public function delPassport($id) {
        if (!$this->isAuthorized()){
            $this->setError('Вы не авторизованы! Сначала <a href="/iom/?act=loginform">войдите под своей учетной записью</a>!');
            return false;
        }
        \QB::table('pass')->where('id', '=', $id)->where('parent', '=', $this->user->id)->where('checked', '=', 0)->delete();
        header('Location: /index.php?act=profile');
    }

    //========================================================================================================================================
    public function delDiplom($id) {
        if (!$this->isAuthorized()){
            $this->setError('Вы не авторизованы! Сначала <a href="/iom/?act=loginform">войдите под своей учетной записью</a>!');
            return false;
        }
        \QB::table('diplom')->where('id', '=', $id)->where('parent', '=', $this->user->id)->where('checked', '=', 0)->delete();
        header('Location: /index.php?act=profile');
    }
    
    //========================================================================================================================================
    protected function copyBtn($param) {
        if ($this->user->rulesR > RULE_VIEW){
            return '<i class="fa-light fa-copy copy-btn show-tooltip"  title="Скопировать значение в буфер обмена" data-copy="'.$param.'"></i>';
        } else {
            return '';
        }
        
    }
    
    //========================================================================================================================================
    public function profile() {
        if (!$this->isAuthorized()){
            $this->setError('Вы не авторизованы! Сначала <a href="/iom/?act=loginform">войдите под своей учетной записью</a>!');
            return false;
        }
        $this->setTitle('Профиль пользователя');
        $this->addContent('<h2 align="center">Профиль пользователя</h2>');
        //Error::pdump($this, 'LK->');
        
        $html = '<div class="formrow shadow round5border dgraybg w100ps margin5updown">'
                . '<p class="tcenter" id="mess"><a href="/iom/?act=messages">Мои уведомления ['. $this->message_count.'] '.($this->new_message_count > 0 ?'<span id="mess_c" title="'. $this->new_message_count.' '
                    . number($this->new_message_count, array('новое уведомление', 'новых уведомления', 'новых уведомлений')).'" class="show-tooltip">'. $this->new_message_count.'</span>' : '')
                .'</a></p>'
                .'</div>';
        

        $html .=  '<h3 id="userinfo">Данные пользователя</h3>';//. '<span id="userinfoblock">';
        
        $html .= '<div id="human" class="formrow shadow round5border dgraybg w100ps">'
                . '<h3>Информация о пользователе'.' <nobr>[ ID: '.$this->user->id.' ]</nobr>' 
                . '<a href="/iom/?act=edituser&id='.$this->user->id.'" style="color: black; text-decoration: none;" class="editicon">'
                . '<i class="fa-light fa-pen-to-square fa-2x show-tooltip" title="Редактировать"></i></span></a></h3>'
                
                .$this->copyBtn($this->user->lastname.' '.$this->user->firstname.' '.$this->user->fathername).'&nbsp;Ф.И.О.: '
                .'<div style="display: inline-block"><strong>'.$this->user->lastname.'</strong></div> '
                .'<div style="display: inline-block"><strong>'.$this->user->firstname.'</strong></div> '
                .'<div style="display: inline-block"><strong>'.$this->user->fathername.'</strong></div>'
                .' <i class="fa-light fa-'.($this->user->sex == 1 ? 'mars' : 'venus').' show-tooltip" title="'.$this->user->sexR.'"></i>'
                .'<br />'.$this->copyBtn($this->user->birthday).'&nbsp;Дата рождения: <strong>'.$this->user->birthday.'</strong>'
                .'<br />'.$this->copyBtn($this->user->snils).'&nbsp;СНИЛС: <strong>'.$this->user->snils.'</strong>'
                .'<br />'.$this->copyBtn($this->user->regionR).'&nbsp;Регион проживания: <strong>'.$this->user->regionR.'</strong>'
                .(!is_null($this->user->distinctrm) ? '<br />'.$this->copyBtn($this->user->distinctrmR).'&nbsp;Район: <strong>'
                        .$this->user->distinctrmR.'</strong>' : '').($this->user->cityrm == 1 ? ' [<small>Город</small>]' : '')
                .(!is_null($this->user->distinct) ? '<br />'.$this->copyBtn($this->user->distinct).'&nbsp;Район: <strong>'
                        .$this->user->distinct.'</strong>' : '')
                .'<br />'.$this->copyBtn($this->user->city).'&nbsp;Населенный пункт: <strong>'.$this->user->city.'</strong>'
                .'<br />'.$this->copyBtn($this->user->address).'&nbsp;Ул., дом, кв.: <strong>'.$this->user->address.'</strong>'
                .'<br />'.$this->copyBtn($this->user->phone).'&nbsp;Телефон: <strong>'.$this->user->phone.'</strong>'
                .'<br />'.$this->copyBtn($this->user->email).'&nbsp;E-mail: <strong>'.$this->user->email.'</strong>'
                .'<br />'.$this->copyBtn($this->user->pedstage).'&nbsp;Педстаж: <strong>'.$this->user->pedstage.'</strong>'
                .'<br><span'.(!is_null($this->user->extfile) ? ' class="scandoc"><a href="/iom/?act=download&file=snils_'
                                .$this->user->id.'.'.$this->user->extfile.'"  target="_blank" title="Скан СНИЛС"><img src="/img/snils.png"></a>' : 
                                '> <span style="color: red">Скан СНИЛС не загружен</span>')
                                .($this->user->checked == 1 ? '<i class="fa-light fa-badge-check fa-2x good" title="Данные подтверждены"></i>' : '').'</span>';
        $html .= '</div>';
      
        $html .= '<h3>Информация о месте работы<a href="/iom/?act=addwork&id='.$this->user->id
                .'" style="color: black; text-decoration: none;" class="addicon"><i class="fa-light fa-circle-plus fa-beat" title="Добавить место работы"></i>'
                . '</a></h3>';
        if (count($this->job) != 0){
            foreach ($this->job as $v) {
                $html .= '<div class="formrow shadow round5border dgraybg w100ps margin5updown">'
                        . ($v->checked != 1 ? '<a href="/iom/?act=editwork&id='.$v->workid
                        .'" style="color: black; text-decoration: none;" class="editicon">'
                        . '<i class="fa-light fa-pen-to-square fa-2x show-tooltip" title="Редактировать"></i></a>' : '').
                        ($v->gosslujba == 1 ? '[<small style="color: green">Является госслужащим</small>]' : '').
                        $this->copyBtn($v->organisation).'&nbsp;Организация: <strong>'.$v->organisation.'</strong>'.
                        '<br />'.$this->copyBtn($v->waddress).'&nbsp;Почтовый адрес: <strong>'.$v->waddress.'</strong>'.
                        '<br />'.$this->copyBtn($v->city).'&nbsp;Населенный пункт: <strong>'.$v->city.'</strong>'.
                        ($v->region == 13 ? '<br />'.$this->copyBtn($v->distinctR).'&nbsp;Район РМ: <strong>'.$v->distinctR.'</strong>' : '').
                        '<br />'.$this->copyBtn($v->regionR).'&nbsp;Регион: <strong>'.$v->regionR.'</strong>'.
                        '<br />'.$this->copyBtn($v->profession).'&nbsp;Должность: <strong>'.$v->profession.'</strong>'.
                        '<br />'.$this->copyBtn($v->stage).'&nbsp;Стаж в должности: <strong>'.$v->stage.'</strong>'.
                        '<br />'.$this->copyBtn($v->phone).'&nbsp;Рабочий телефон: <strong>'.$v->phone.'</strong>';
                //Error::pdump($v);
                $html .= '</div>';
            }
        }
        
        $html .= '<h3 id="passheader">Паспортов в системе: '.count($this->pass).
                     '<a href="/iom/?act=addpassport&id='.$this->user->id.'" style="color: black; text-decoration: none;" class="addicon">'
                    . '<i class="fa-light fa-circle-plus fa-beat" title="Добавить паспорт"></i></a></h3>';
        if (count($this->pass) != 0){
            foreach ($this->pass as $v) {
                $html .= '<div class="formrow shadow round5border dgraybg w100ps margin5updown'.($v->checked == 1 ? ' ochecked' : '').'">'.
                        ($v->checked == 0 ? '<a href="/iom/?act=editpassport&id='.$v->id.'" style="color: black; text-decoration: none;" '
                            . 'class="editicon"><i class="fa-light fa-pen-to-square fa-2x show-tooltip" title="Редактировать"></i></a>' : '').
                        ($v->checked == 0 ? '<a href="/iom/?act=delpassport&id='.$v->id.'" style="color: black; text-decoration: none;" '
                            . 'class="editicon" onclick="if(window.confirm(\'Внимание!\nОтмена данного действия будет невозможна!\n'
                            . 'Вы уверены, что хотите удалить?\')==true) {return true;} else {return false;}"><i class="fa-light fa-trash-can fa-2x show-tooltip" '
                            . 'title="Удалить"></i></a>' : '') 
                        .$this->copyBtn($v->citizenship).'&nbsp;Гражданство: <strong>'.$v->citizenship.'</strong>'.
                        '<br />'.$this->copyBtn($v->series.'-'.$v->number).'&nbsp;Номер: <strong>'.$v->series.'-'.$v->number.'</strong>'.
                        '<br />'.$this->copyBtn($v->info).'&nbsp;Выдан:     <strong>'.$v->info.'</strong>'.
                        '<br />'.$this->copyBtn($v->datedoc).'&nbsp;Дата выдачи: <strong>'.$v->datedoc.'</strong> '.
                        '<br><span'
                        .(!is_null($v->extfile) ? ' class="scandoc"><a href="/iom/?act=download&file=pass_'.$v->id.'.'.$v->extfile
                                    .'"  target="_blank" title="Скан паспорта"><img src="/img/passport.png"></a>' : 
                                   '> <span style="color: red">Скан паспорта не загружен</span>')
                        .'</span>';
                //Error::pdump($v);
                $html .= '</div>';
            }
        }

        $html .= '<h3>Дипломов в системе: '.count($this->diplom).
                    '<a href="/iom/?act=adddiplom&id='.$this->user->id.'" style="color: black; text-decoration: none;" class="addicon">'
                . '<i class="fa-light fa-circle-plus fa-beat" title="Добавить диплом"></i></a></h3>';
        if (count($this->diplom) != 0){
            foreach ($this->diplom as $v) {
                $html .= '<div class="formrow shadow round5border dgraybg w100ps margin5updown'.($v->checked == 1 ? ' ochecked' : '').'">'.
                        ($v->checked == 0 ? '<a href="/iom/?act=editdiplom&id='.$v->id.'" style="color: black; text-decoration: none;" '
                            . 'class="editicon"><i class="fa-light fa-pen-to-square fa-2x show-tooltip" title="Редактировать"></i></a>' : '').
                        ($v->checked == 0 ? '<a href="/iom/?act=deldiplom&id='.$v->id.'" style="color: black; text-decoration: none;" '
                            . 'class="editicon"  onclick="if(window.confirm(\'Внимание!\nОтмена данного действия будет невозможна!\n'
                            . 'Вы уверены, что хотите удалить?\')==true) {return true;} else {return false;}">'
                            . '<i class="fa-light fa-trash-can fa-2x show-tooltip" title="Удалить"></i></a>' : '')
                        .$this->copyBtn($v->level).'&nbsp;Уровень образования: <strong>'.$v->level.'</strong>'.
                        '<br />'.$this->copyBtn($v->almamatter).'&nbsp;ВУЗ, ССУЗ: <strong>'.$v->almamatter.'</strong>'.
                        '<br />'.$this->copyBtn($v->series.'-'.$v->number).'&nbsp;Номер диплома: <strong>'.$v->series.'-'.$v->number.'</strong>'.
                        '<br />'.$this->copyBtn($v->regnumber).'&nbsp;Рег. номер: <strong>'.$v->regnumber.'</strong>'.
                        '<br />'.$this->copyBtn($v->datedoc).'&nbsp;Выдан: <strong>'.$v->datedoc.'</strong>'.
                        '<br />'.$this->copyBtn($v->qualification).'&nbsp;Квалификация: <strong>'.$v->qualification.'</strong>'.
                        '<br />'.$this->copyBtn($v->stepen).'&nbsp;Степень: <strong>'.$v->stepen.'</strong>'.
                        '<br />'.$this->copyBtn($v->zvanie).'&nbsp;Звание: <strong>'.$v->zvanie.'</strong>'.
                        ((!is_null($v->f) || !is_null($v->i) || !is_null($v->o)) ? '<br />'.$this->copyBtn($v->f.' '.$v->i.' '.$v->o)
                                .'&nbsp;Ф.И.О. в дипломе: <strong>'.$v->f.' '.$v->i.' '.$v->o.'</strong>' : '').
                        '<br><span'
                        .(!is_null($v->dextfile) ? ' class="scandoc"><a href="/iom/?act=download&file=diplom_'.$v->id.'.'.$v->dextfile
                                .'"  target="_blank" title="Скан диплома"><img src="/img/diplom.png"></a>' : '> '
                                . '<span style="color: red">Скан диплома не загружен</span>')
                        .'</span>'.
                        ( (!is_null($v->f) || !is_null($v->i) || !is_null($v->o)) 
                                ? (!is_null($v->fextfile) 
                                        ? '<span class="scandoc"><a href="/iom/?act=download&file=fio_'.$v->id.'.'.$v->fextfile.'"  '
                                        . 'target="_blank" title="Скан документа о смене ФИО"><img src="/img/fio.png"></a></span>' 
                                        : '<span><span style="color: red">Скан документа о смене ФИО не загружен</span>'
                                   ) 
                                : ''
                        );
                //Error::pdump($v);
                $html .= '</div>';
            }
        }
        
        $html .= '</div>';
        //$this->addJQCode('$("#userinfo").on("click", function(){ $(this).parent().attr("class", ""); $("#userinfoblock").toggle();});');
        $this->addContent($html);
    }
    
    //========================================================================================================================================
    public static function encrypt($text) {
            $ivlen = openssl_cipher_iv_length($cipher="AES-128-CBC");
            $iv = openssl_random_pseudo_bytes($ivlen);
            $ciphertext_raw = openssl_encrypt($text, $cipher, ENCRYPTION_KEY, $options=OPENSSL_RAW_DATA, $iv);
            $hmac = hash_hmac('sha256', $ciphertext_raw, ENCRYPTION_KEY, $as_binary=true);
            $ciphertext = base64_encode( $iv.$hmac.$ciphertext_raw );
            return $ciphertext;
        }
        
    //========================================================================================================================================
    public static function decrypt($text) {
            $c = base64_decode($text);
            $ivlen = openssl_cipher_iv_length($cipher="AES-128-CBC");
            $iv = substr($c, 0, $ivlen);
            $hmac = substr($c, $ivlen, $sha2len=32);
            $ciphertext_raw = substr($c, $ivlen+$sha2len);
            $plaintext = openssl_decrypt($ciphertext_raw, $cipher, ENCRYPTION_KEY, $options=OPENSSL_RAW_DATA, $iv);
            $calcmac = hash_hmac('sha256', $ciphertext_raw, ENCRYPTION_KEY, $as_binary=true);
            if (hash_equals($hmac, $calcmac))
            {
                return $plaintext;
            }
        }
        
    //=========================================================================================================================================
    public function sendMail($address, $subject, $body, $okMessage) {
        $mail = new PHPMailer(true);

            try {
                //Server settings
                $mail->setLanguage('ru');
                $mail->SMTPDebug = SMTP::DEBUG_OFF;                      //Enable verbose debug output
                $mail->isSMTP();                                            //Send using SMTP
                $mail->Host       = 'smtp.yandex.ru';                     //Set the SMTP server to send through
                $mail->SMTPAuth   = true;                                   //Enable SMTP authentication
                $mail->Username   = 'no-reply.cnppm';                     //SMTP username
                $mail->Password   = 'mlrnhjvnkhckokau';                               //SMTP password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;            //Enable implicit TLS encryption
                $mail->Port       = 465;                                    //TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`
                $mail->CharSet = PHPMailer::CHARSET_UTF8;
                //Recipients
                $mail->setFrom('no-reply.cnppm@yandex.ru', 'ЦНППМ "Педагог 13.ру"');
                $mail->addAddress(trim($address));     //Add a recipient

                //Content
                $mail->isHTML(true);                                  //Set email format to HTML
                $mail->Subject = $subject;
                $mail->Body    = '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />'.$body;
                $mail->send();
                $this->addContent($okMessage);
            } catch (Exception $e) {
                $this->setError('Не удалось отправить письмо.<br />'.$mail->ErrorInfo);
            }
    }
    
    //===============================================================================================================================

    //===============================================================================================================================

    //=====================================================================================================================================
    public function fileDownload($file) {
        Error::pdump('Начинаем качать');
        if (!$this->isAuthorized()){
            $this->setError('Вы не авторизованы! Сначала <a href="/iom/?act=loginform">войдите под своей учетной записью</a>!');
            return false;
        }
        Error::pdump('Закачка авторизована');
        if (!in_array($file, $this->files)){
            $this->setError('Ай-ай-ай, как некрасиво >:(');
                return false;
        }
        Error::pdump('Файл наш');
        Error::pdump('файл - '.uploaddir.$file);
        if (file_exists(uploaddir.$file)) {
            Error::pdump('файл существует '.uploaddir.$file);
          // сбрасываем буфер вывода PHP, чтобы избежать переполнения памяти выделенной под скрипт
          // если этого не сделать файл будет читаться в память полностью!
          if (ob_get_level()) {
            ob_end_clean();
          }
          // заставляем браузер показать окно сохранения файла
          header('Content-Description: File Transfer');
          header('Content-Type: application/octet-stream');
          header('Content-Disposition: attachment; filename=' . $this->user->lastname.'_'.$this->user->firstname.'_'.$this->user->fathername.'-'.$file);
          header('Content-Transfer-Encoding: binary');
          header('Expires: 0');
          header('Cache-Control: must-revalidate');
          header('Pragma: public');
          header('Content-Length: ' . filesize(uploaddir.$file));
          // читаем файл и отправляем его пользователю
          readfile(uploaddir.$file);
          exit;
        }
    }
    
    //========================================================================================================================================
    public function uploadImage() {
        if (!$this->isAuthorized() || !$this->isRule(RULE_MODERATE)){
            $this->setError('Вы не имеете прав на данное действие!');
            return false;
        }
        /***************************************************
         * Only these origins are allowed to upload images *
         ***************************************************/
         $accepted_origins = array("https://localhost", SITE);

         /*********************************************
          * Change this line to set the upload folder *
          *********************************************/
         $imageFolder = "upload/images/";

         if (isset($_SERVER['HTTP_ORIGIN'])) {
           // same-origin requests won't set an origin. If the origin is set, it must be valid.
           if (in_array($_SERVER['HTTP_ORIGIN'], $accepted_origins)) {
             header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
           } else {
             header("HTTP/1.1 403 Origin Denied");
             return;
           }
         }

         // Don't attempt to process the upload on an OPTIONS request
         if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
           header("Access-Control-Allow-Methods: POST, OPTIONS");
           return;
         }

         reset ($_FILES);
         $temp = current($_FILES);
         if (is_uploaded_file($temp['tmp_name'])){
           /*
             If your script needs to receive cookies, set images_upload_credentials : true in
             the configuration and enable the following two headers.
           */
           // header('Access-Control-Allow-Credentials: true');
           // header('P3P: CP="There is no P3P policy."');

           // Sanitize input
           if (preg_match("/([^\w\s\d\-_~,;:\[\]\(\).])|([\.]{2,})/", $temp['name'])) {
               header("HTTP/1.1 400 Invalid file name.");
               return;
           }

           // Verify extension
           if (!in_array(strtolower(pathinfo($temp['name'], PATHINFO_EXTENSION)), array("gif", "jpg", "png"))) {
               header("HTTP/1.1 400 Invalid extension.");
               return;
           }

           // Accept upload if there was no origin, or if it is an accepted origin
           $filetowrite = $imageFolder .date('d-m-Y_H-i_'). \OnlineRecord\RusToLat($temp['name']);
           //$filetowrite = $imageFolder .date('d-m-Y_H-i_'). $temp['name'];
           move_uploaded_file($temp['tmp_name'], $filetowrite);

           // Determine the base URL
           $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? "https://" : "http://";
           $baseurl = $protocol . $_SERVER["HTTP_HOST"] . rtrim(dirname($_SERVER['REQUEST_URI']), "/") . "/";

           // Respond to the successful upload with JSON.
           // Use a location key to specify the path to the saved image resource.
           // { location : '/your/uploaded/image/file'}
           echo json_encode(array('location' => $baseurl . $filetowrite));
         } else {
           // Notify editor that the upload failed
           header("HTTP/1.1 500 Server Error");
         }

    }// function uploadImage
    
        
    public function test($param = null) {
        $data = array('regclosed' => 1);
        $i = \QB::table('course')->where('course.id', 968)->update($data);
        Error::pdump($i);
        die();
    }

    public function showIomList()
    {
        $this->addContent('Выбор тестов');
        $zip = new \ZipArchive();
//        phpinfo();
    }
}//class LKiom