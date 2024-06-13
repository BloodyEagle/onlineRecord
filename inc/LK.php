<?php
namespace OnlineRecord;

use OnlineRecord\Error as Error;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
use OnlineRecord\DocCreator;
use Pixie\Connection;

const RULE_VIEW = 1;//просмотр списка курсов
const RULE_MODERATE = 2;//добавление | правка курсов
const RULE_REPORTS = 4;//доступ к отчетам
const RULE_CATALOGUE = 8;//правка справочников
const RULE_CATEGORY = 16;//правка дерева категорий курсов
const RULE_DOCS = 32;//доступ к приказам
const RULE_ADMIN =  128;//права админа

const APP_ALL = 0;//Все заявки на курсы

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
const READY_ALL = 4;//Все данные Загружены

const USER_LIST_SEARCH = 1;//Поиск пользователя
const USER_LIST_GROUP = 2;//Группы пользователей

class LK
{
    protected $connection;

    private $error = false;
    private $error_message = null;
    private $authorized = false;

    private $Ghtml;
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
    private $applicationCount;//количество заявок
    private $appChangedCount;//количество заявок, сменивших статус
    private $appModerCount;//количество заявок на ваши курсы

    private $codeAdded = false;

    public function __construct()
    {
        $this->templates = array('{%HEADERS%}' => null,
            '{%TITLE%}' => null,
            '{%PROFILE%}' => null,
            '{%JSCODE%}' => null,
            '{%JQCODE%}' => null,
            '{%ERRORS%}' => null,
            '{%SHOWERRORS%}' => 'style="display: none;"',
            '{%CONTENT%}' => null,
            '{%MODAL CONTENT%}' => null);
    }

    function __destruct()
    {
    }

    //========================================================================================================================================
    public function isAuthorized()
    {
        return $this->authorized;
    }

    /** ========================================================================================================================================
     * разлогинивает пользователя
     */
    public function logout()
    {
        setcookie('onlineRecord', 0, time() - 1000, '/', $_SERVER['SERVER_NAME']);
        $this->addRedirect(SITE, 0);
    }

    /**========================================================================================================================================
     * Поднимает флаг ошибки и записывает ошибку в буфер
     */
    public function setError($msg)
    {
        $this->error = true;
        $this->templates['{%SHOWERRORS%}'] = 'style="display: block;"';
        $this->templates['{%ERRORS%}'] .= (is_null($this->templates['{%ERRORS%}']) ? $msg : '<br />' . $msg);

    }

    /**========================================================================================================================================
     * Устанавливает значение тега <title>
     */
    public function setTitle($msg)
    {
        $this->templates['{%TITLE%}'] = $msg;
    }

    /** ========================================================================================================================================
     * Добавляет HTML заголовок в секцию <HEAD>
     */
    public function addHeader($msg)
    {
        $this->templates['{%HEADERS%}'] .= $msg;
    }

    /** ========================================================================================================================================
     * Добавляет редирект на страницу
     * @param String $address - адрес, на который делается редирект
     * @param int $time - задержка, сек
     */
    public function addRedirect($address, $time)
    {
        $this->addHeader('<meta http-equiv="refresh" content="' . (int)$time . '; URL=' . $address . '" />');
    }


    /** ========================================================================================================================================
     * Добавляет контент в основной блок страницы
     * @param string $msg - контент
     */
    public function addContent($msg)
    {
        $this->templates['{%CONTENT%}'] .= $msg;
    }


    /** ========================================================================================================================================
     * Добавляет шаблон для замены $search на $replace на странице
     * @param string $search - строка поиска
     * @param string $replace - строка замены
     */
    public function addRtemplate($search, $replace)
    {
        $this->templates[$search] = $replace;
    }

    public function addModalContent($content)
    {
        $this->addRtemplate('{%MODAL CONTENT%}', $content);
    }

    /** ========================================================================================================================================
     * Добавляет нативный JavaScript код в <head> секцию
     * @param string $msg - строка кода
     */
    public function addJSCode($msg)
    {
        $this->codeAdded = true;
        $this->templates['{%JSCODE%}'] .= $msg;
    }

    /** ========================================================================================================================================
     * Добавляет jQuery код  в <head> секцию внутрь блока $(document).ready
     * @param string $msg - строка кода
     */
    public function addJQCode($msg)
    {
        $this->codeAdded = true;
        $this->templates['{%JQCODE%}'] .= $msg . "\n";
    }


    /** ========================================================================================================================================
     * Генерирует сообщение для пользователя
     * @param String $ttl - заголовок сообщения
     * @param String $msg - текст сообщения
     * @param Int $owner - ID пользователя, для кого сообщение
     * @param String $link - ссылка
     * @return boolean - true если все ок
     */
    public function addMessage($ttl, $msg, $owner = null, $link = null)
    {
        $data = array(
            'title' => $ttl,
            'message' => $msg,
            'link' => $link ?? null,
            'owner' => (is_null($owner) ? $this->user->id : $owner)
        );

        $insertId = \QB::table('messages')->insert($data);
        if (is_null($insertId)) {
            $this->addContent('Ошибка записи сообщения в базу!');
            return false;
        } else {
            return true;
        }
    }


    /** ========================================================================================================================================
     * Выводит в контент форму для входа
     */
    public function loginForm()
    {
        $this->addContent(file_get_contents(dirname(__FILE__) . '/loginForm.html'));
        $this->addRtemplate('{%CATID%}', (isset($_REQUEST['cat']) ? $_REQUEST['cat'] : ''));
        $this->setTitle('Форма авторизации');
        //$this->parseHtml($this->templates);

    }

    /** ========================================================================================================================================
     * Выводит в контент форму для восстановления пароля
     */
    public function lostPassForm()
    {
        $html = file_get_contents(dirname(__FILE__) . '/lostpassForm.html');
        $js = file_get_contents(dirname(__FILE__) . '/lostpassForm.js');
        $jqs = file_get_contents(dirname(__FILE__) . '/lostpassForm.jqs');
        $this->setTitle('Восстановление пароля');
        $this->addContent($html);
        $this->addJSCode($js);
        $this->addJQCode($jqs);

    }

    /** ========================================================================================================================================
     * Выводит в контент форму для регистрации
     */
    public function regForm()
    {
        $this->addRtemplate('{%REGIONLIST%}', $this->getRegions());
        $this->addRtemplate('{%WREGIONLIST%}', $this->getRegions(null, true));
        $this->addRtemplate('{%SEXLIST%}', $this->getSex());
        $this->addRtemplate('{%REGRMLIST%}', $this->getDistinct());
        $this->addRtemplate('{%WREGRMLIST%}', $this->getDistinct(null, true));
        $this->addRtemplate('{%CITIZENSHIPLIST%}', $this->getCountries());
        $this->addRtemplate('{%EDULEVELLIST%}', $this->getEdulevel());
        $this->addRtemplate('{%REQUIRED PASSPORT SCAN%}', Settings::getSetting('requiredPassportScan') == 1 ? 'class="rfield"' : '');
        $this->addRtemplate('{%REQUIRED DIPLOM SCAN%}', Settings::getSetting('requiredDiplomScan') == 1 ? 'class="rfield"' : '');
        $this->addRtemplate('{%REQUIRED FIO SCAN%}', Settings::getSetting('requiredFioScan') == 1 ? 'class="rfield"' : '');
        $this->addRtemplate('{%REQUIRED SNILS SCAN%}', Settings::getSetting('requiredSnilsScan') == 1 ? 'class="rfield"' : '');
        $this->addRtemplate('{%REQUIRED SNILS MARK%}', Settings::getSetting('requiredSnilsScan') == 1 ? '<span style="color: red;">*</span>' : '');
        $this->addRtemplate('{%REQUIRED PASSPORT MARK%}', Settings::getSetting('requiredPassportScan') == 1 ? '<span style="color: red;">*</span>' : '');
        $this->addRtemplate('{%REQUIRED DIPLOM MARK%}', Settings::getSetting('requiredDiplomScan') == 1 ? '<span style="color: red;">*</span>' : '');
        $this->addRtemplate('{%HIDE FIELD SNILS SCAN%}', Settings::getSetting('hideFieldSnilsScan') == 1 ? 'hidden' : '');
        $this->setTitle('Регистрация нового пользователя');

        $this->addHeader('<link rel="stylesheet" href="inc/select2.css">');
        $this->addHeader('<script src="inc/select2.full.js"></script>');
        $js = "$('#region').select2({
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

        $this->addContent(file_get_contents(dirname(__FILE__) . '/regForm.html'));
        //$this->parseHtml($this->templates);
    }

    /** ========================================================================================================================================
     * Выводит в контент форму для добавления\сохранения данных пользовтаеля
     * @param int $id - ID пользователя. Указывается только для редактирования
     * @return boolean
     */
    public function userInfo($id = null)
    {
        if (!$this->isAuthorized()) {
            $this->setError('Вы не авторизованы! Сначала <a href="/?act=loginform">войдите под своей учетной записью</a>!');
            return false;
        }
        $edit = false;
        if (!is_null($id)) {
            $edit = true;
            $query = \QB::table('users')->select(array(
                'users.id', 'users.snils', 'users.extfile',
                'users.group', \QB::raw(\OnlineRecord\config['prefix'] . "ugroup.group AS groupR"),
                'users.rules', \QB::raw(\OnlineRecord\config['prefix'] . "ugroup.rules AS grules"),
                'users.added', 'users.closed',
                'users.firstname', 'users.lastname', 'users.fathername',
                'users.sex', //\QB::raw(\OnlineRecord\config['prefix']."sex.sex AS sexR"),
                \QB::raw("DATE_FORMAT(" . \OnlineRecord\config['prefix'] . "users.birthday, '%d.%m.%Y') AS birthday"),
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
                ->where('users.id', $id)
                ->where('activated', '=', 1);
            //->where('banned', '=', 0);
            $r = $query->first();
            //$this->addJQCode('$(".formrow input[type=text], select").after("<input type=\"checkbox\" style=\"position: absolute; margin-left: 17%; \">");');
            $this->addJQCode('$("#id").after("<input type=\"hidden\" name=\"moder\" value=\"1\">").after("<input type=\"hidden\" name=\"referer\" value=\"' . (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : $_REQUEST['referer']) . '\">");');
            if ($this->isRule(RULE_MODERATE)) {
                $this->addJQCode('$("#save").val("Сохранить данные").after("<input type=\"submit\" name=\"check\" id=\"check\" value=\"Подтвердить данные\">");');
                $this->addJQCode('$("#check").on("click", function(){ location.href="/?act=checkdata&user=' . $r->id . '&referer=' . (isset($_REQUEST['referer']) ? $_REQUEST['referer'] : $_SERVER['HTTP_REFERER']) . '"; return false; });');
            } else {
                $this->addJQCode('$("#save").val("Сохранить данные")');
            }
        }

        $this->addRtemplate('{%USERID%}', $edit ? $r->id : $this->user->id);
        $this->addRtemplate('{%LASTNAME%}', $edit ? $r->lastname : $this->user->lastname);
        $this->addRtemplate('{%FIRSTNAME%}', $edit ? $r->firstname : $this->user->firstname);
        $this->addRtemplate('{%FATHERNAME%}', $edit ? $r->fathername : $this->user->fathername);
        $this->addRtemplate('{%SNILS%}', $edit ? $r->snils : $this->user->snils);
        $this->addRtemplate('{%SSCAN%}', (is_null($edit ? $r->extfile : $this->user->extfile) ? '<span class="wrong">Скан СНИЛС не был прикреплен!</span>' :
            'Скан СНИЛС: <a href="/?act=download&file=snils_' . ($edit ? $r->id : $this->user->id) . '.' . ($edit ? $r->extfile : $this->user->extfile)
            . '"target="_blank"><img src="/img/snils.png"></a><label><input type="checkbox" id="changescan">Заменить скан</label>'));
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
        if (!is_null($edit ? $r->extfile : $this->user->extfile)) $this->addJQCode(file_get_contents(dirname(__FILE__) . '/passInfo.jqs'));
        $this->addRtemplate('{%REGIONLIST%}', $this->getRegions($edit ? $r->region : $this->user->region));
        $this->addRtemplate('{%SEXLIST%}', $this->getSex($edit ? $r->sex : $this->user->sex));
        $this->addRtemplate('{%REGRMLIST%}', $this->getDistinct($edit ? $r->distinctrm : $this->user->distinctrm));
        $this->addContent(file_get_contents(dirname(__FILE__) . '/userInfo.html'));

        $this->addHeader('<link rel="stylesheet" href="inc/select2.css">');
        $this->addHeader('<script src="inc/select2.full.js"></script>');
        $js = "$('#region').select2({
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
        return true;
    }

    /** ========================================================================================================================================
     * Выводит в контент форму для добавления\сохранения данных паспорта пользовтаеля
     * @param int $id - ID ПАСПОРТА. Указывается только для редактирования
     * @return boolean
     */
    public function passInfo($id = null)
    {
        if (!$this->isAuthorized()) {
            $this->setError('Вы не авторизованы! Сначала <a href="/?act=loginform">войдите под своей учетной записью</a>!');
            return false;
        }
        Error::pdump($id, 'id');
        $edit = false;
        if (!is_null($id)) {
            $edit = true;
            foreach ($this->pass as $r) {
                if ($r->id == $id) {
                    Error::pdump($r, 'r');
                    break;
                }
            }
            if ($r->id != $id) {
                Error::pdump('Паспорт не мой');
                $query = \QB::table('pass')->select(array(
                    'pass.id',
                    \QB::raw(\OnlineRecord\config['prefix'] . "pass.citizenship AS citizen"),
                    'pass.series',
                    'pass.number',
                    \QB::raw("DATE_FORMAT(" . \OnlineRecord\config['prefix'] . "pass.datedoc, '%d.%m.%Y') AS datedoc"),
                    'pass.info',
                    'pass.extfile',
                    'pass.parent',
                    'pass.checked'))
                    ->where('pass.id', $id);
                $r = $query->first();
                $this->addJQCode('$("#id").after("<input type=\"hidden\" name=\"moder\" value=\"1\">").after("<input type=\"hidden\" name=\"referer\" value=\"' . (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : $_REQUEST['referer']) . '\">");');
                $this->addJQCode('$("#save").val("Сохранить данные").after("<input type=\"submit\" name=\"check\" id=\"check\" value=\"Подтвердить данные\">");');
                $this->addJQCode('$("#check").on("click", function(){ location.href="/?act=checkdata&pass=' . $r->id . '&referer=' . (isset($_REQUEST['referer']) ? $_REQUEST['referer'] : $_SERVER['HTTP_REFERER']) . '"; return false; });');
            }
        }
        $this->addRtemplate('{%PASSID%}', !is_null($id) ? $r->id : '');;
        $this->addRtemplate('{%CITIZENSHIPLIST%}', !is_null($id) ? $this->getCountries($r->citizen) : $this->getCountries());
        $this->addRtemplate('{%PSERIES%}', !is_null($id) ? $r->series : '');
        $this->addRtemplate('{%PNUMBER%}', !is_null($id) ? $r->number : '');
        $this->addRtemplate('{%PDATE%}', !is_null($id) ? $r->datedoc : '');
        $this->addRtemplate('{%PINFO%}', !is_null($id) ? $r->info : '');
        $this->addRtemplate('{%PSCAN%}', !is_null($id) ? (is_null($r->extfile) ? 'Скан паспорта не был прикреплен!' :
            'Скан паспорта: <a href="/?act=download&file=pass_' . $r->id . '.' . $r->extfile
            . '"target="_blank"><img src="/img/passport.png"></a><label><input type="checkbox" id="changescan">Заменить скан</label>') : '');
        if (!is_null($id)) {
            if (!is_null($r->extfile)) $this->addJQCode(file_get_contents(dirname(__FILE__) . '/passInfo.jqs'));
        }
        $this->addContent(file_get_contents(dirname(__FILE__) . '/passInfo.html'));
        return true;
    }

    /** ========================================================================================================================================
     * Выводит в контент форму для добавления\сохранения данных о работе пользовтаеля
     * @param int $id - ID работы. Указывается только для редактирования
     * @return boolean
     */
    public function workInfo($id = null)
    {
        if (!$this->isAuthorized()) {
            $this->setError('Вы не авторизованы! Сначала <a href="/?act=loginform">войдите под своей учетной записью</a>!');
            return false;
        }
        $edit = false;
        if (!is_null($id)) {
            $edit = true;
            foreach ($this->job as $r) {
                if ($r->workid == $id)
                    break;
            }
            if ($r->workid != $id) {
                Error::pdump('Работа не моя');
                $r = \QB::table('work')->find($id);
                $r->workid = $r->id;
                //$r = $query->first();
                $this->addJQCode('$("#id").after("<input type=\"hidden\" name=\"moder\" value=\"1\">").after("<input type=\"hidden\" name=\"referer\" value=\"' . (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : $_REQUEST['referer']) . '\">");');
                $this->addJQCode('$("#save").val("Сохранить данные").after("<input type=\"submit\" name=\"check\" id=\"check\" value=\"Подтвердить данные\">");');
                $this->addJQCode('$("#check").on("click", function(){ location.href="/?act=checkdata&work=' . $r->id . '&referer=' . (isset($_REQUEST['referer']) ? $_REQUEST['referer'] : $_SERVER['HTTP_REFERER']) . '"; return false; });');
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
        $this->addContent(file_get_contents(dirname(__FILE__) . '/workInfo.html'));

        $this->addHeader('<link rel="stylesheet" href="inc/select2.css">');
        $this->addHeader('<script src="inc/select2.full.js"></script>');
        $js = "$('#wregion').select2({
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
        return true;
    }

    /** ========================================================================================================================================
     * Выводит в контент форму для добавления\сохранения данных диплома пользовтаеля
     * @param int $id - ID диплома. Указывается только для редактирования
     * @return boolean
     */
    public function diplomInfo($id = null)
    {
        if (!$this->isAuthorized()) {
            $this->setError('Вы не авторизованы! Сначала <a href="/?act=loginform">войдите под своей учетной записью</a>!');
            return false;
        }
        $edit = false;
        if (!is_null($id)) {
            $edit = true;
            foreach ($this->diplom as $r) {
                if ($r->id == $id)
                    break;
            }
            if ($r->id != $id) {
                Error::pdump('Диплом не мой');
                $query = \QB::table('diplom')->select('*')->select(\QB::raw("DATE_FORMAT(" . \OnlineRecord\config['prefix'] . "diplom.datedoc, '%d.%m.%Y') AS datedoc"))->where('diplom.id', $id);
                $r = $query->first();
                Error::pdump($r, "r");

                $this->addJQCode('$("#id").after("<input type=\"hidden\" name=\"moder\" value=\"1\">").after("<input type=\"hidden\" name=\"referer\" value=\"' . (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : $_REQUEST['referer']) . '\">");');
                $this->addJQCode('$("#save").val("Сохранить данные").after("<input type=\"submit\" name=\"check\" id=\"check\" value=\"Подтвердить данные\">");');
                $this->addJQCode('$("#check").on("click", function(){ location.href="/?act=checkdata&diplom=' . $r->id . '&referer=' . (isset($_REQUEST['referer']) ? $_REQUEST['referer'] : $_SERVER['HTTP_REFERER']) . '"; return false; });');
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
        $this->addRtemplate('{%FIOCHECKED%}', !is_null($id) ? ((is_null($r->f) && is_null($r->i) && is_null($r->o)) ? '' : 'checked="checked"') : '');
        $this->addRtemplate('{%DF%}', !is_null($id) ? $r->f : '');
        $this->addRtemplate('{%DI%}', !is_null($id) ? $r->i : '');
        $this->addRtemplate('{%DO%}', !is_null($id) ? $r->o : '');
        $this->addRtemplate('{%FSCAN%}', !is_null($id) ? (is_null($r->fextfile) ? '<p class="c" style="color: red">Скан документа о смене ФИО не был прикреплен!</p>' :
            'Скан документа о смене ФИО: <a href="/?act=download&file=fio_' . $r->id . '.' . $r->fextfile
            . '"target="_blank"><img src="/img/fio.png"></a><label><input type="checkbox" id="changefscan">Заменить скан</label>') : '');
        if (!is_null($id))
            if (!is_null($r->fextfile)) $this->addJQCode("$('#ffield').hide();");
        $this->addRtemplate('{%DSCAN%}', !is_null($id) ? (is_null($r->dextfile) ? '<p class="c" style="color: red">Скан диплома не был прикреплен!</p>' :
            'Скан диплома: <a href="/?act=download&file=diplom_' . $r->id . '.' . $r->dextfile
            . '"target="_blank"><img src="/img/diplom.png"></a><label><input type="checkbox" id="changescan">Заменить скан</label>') : '');
        if (!is_null($id))
            if (!is_null($r->dextfile)) $this->addJQCode(file_get_contents(dirname(__FILE__) . '/diplomInfo.jqs'));
        $this->addContent(file_get_contents(dirname(__FILE__) . '/diplomInfo.html'));
        return true;
    }

    /** ========================================================================================================================================
     * Выводит в контент форму регистрации пользовтаеля
     */
    public function register()
    {
        if (!isset($_REQUEST['accept'])) {
            $this->setError('Необходимо согласиться с политикой обработки персональных данных!');
            return false;
        }

        if ($_REQUEST['pass1'] <> $_REQUEST['pass2']) {
            $this->setError('Введеные пароли не совпадают!');
            return false;
        }

        $err = '';
        if (!validateSnils($_REQUEST['snils'], $err)) {
            $this->setError($err);
            return false;
        }

        $query = \QB::table('users')->where('snils', '=', trim($_REQUEST['snils']))
            ->orWhere('phone', '=', trim($_REQUEST['phone']))->orWhere('email', '=', trim($_REQUEST['email']));
        $result = $query->count();

        if ($result != 0) {
            $this->setError('Пользователь с такими данными (СНИЛС, телефон или e-mail) уже существует! Вы можете иметь только одну '
                . 'учетную запись на сайте.<br />Войдите со своими учетными данными или проверьте введённые данные. '
                . 'Если вы не помните пароль, воспользуйтесь <a href="/?act=recpassform">формой восстановления пароля</a>.');
            return false;
        }

        $dt = \DateTime::createFromFormat('d.m.Y', $_REQUEST['birthday']);

        $data = array(
            'snils' => trim($_REQUEST['snils']),
            'lastname' => trim($_REQUEST['lastname']),
            'firstname' => trim($_REQUEST['firstname']),
            'fathername' => trim($_REQUEST['fathername']),
            'sex' => $_REQUEST['sex'],
            'birthday' => $dt->format('Y-m-d'),
            'phone' => trim($_REQUEST['phone']),
            'email' => trim($_REQUEST['email']),
            'pedstage' => $_REQUEST['stage'],
            'password' => password_hash($_REQUEST['pass1'], PASSWORD_BCRYPT),
            'region' => $_REQUEST['region'],
            'city' => trim($_REQUEST['city']),
            'distinctrm' => ($_REQUEST['region'] == 13 ? $_REQUEST['regionrm'] : null),
            'distinct' => ($_REQUEST['region'] != 13 ? $_REQUEST['distinct'] : null),
            'address' => trim($_REQUEST['address']),
            'cityrm' => (isset($_REQUEST['cityrm']) ? 1 : 0),
            //'activated' => 1,
            'rpass' => $_REQUEST['pass1'],
            'rpass2' => $_REQUEST['pass1'],
        );
        $insertId = \QB::table('users')->insert($data);
        Error::pdump('Пишу чела в базу, id - ' . $insertId);

        if ($_FILES['sscan']['tmp_name'] != '') {
            $extfile = substr(strrchr($_FILES['sscan']['name'], '.'), 1);
            $uploadfile = uploaddir . 'snils_' . $insertId . '.' . $extfile;
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
            if (is_null($insertId)) {
                $this->setError('Ошибка записи данных пользователя в базу!');
                return false;
            }
        }
        if ($_REQUEST['pdata'] != '')
            $dt = \DateTime::createFromFormat('d.m.Y', $_REQUEST['pdata']);
        /*        else
            $dt = new \DateTime('now');*/

        $data = array(
            'citizenship' => $_REQUEST['citizenship'],
            'series' => ($_REQUEST['pseries'] == '' ? NULL : trim($_REQUEST['pseries'])),
            'number' => ($_REQUEST['pnumber'] == '' ? NULL : trim($_REQUEST['pnumber'])),
            'datedoc' => (!$dt ? null : $dt->format('Y-m-d')),
            'info' => ($_REQUEST['pvidan'] == '' ? NULL : $_REQUEST['pvidan']),
            'parent' => $insertId,
            'used' => 1
        );
        $insertIdpass = \QB::table('pass')->insert($data);
        Error::pdump('Пишу паспорт в базу, id - ' . $insertIdpass);
/*        $extfile = substr(strrchr($_FILES['pscan']['name'], '.'), 1);

        if ($_FILES['pscan']['name'] != '') {
            $uploadfile = uploaddir . 'pass_' . $insertIdpass . '.' . $extfile;
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
        }*/
        if ((int)$_REQUEST['edulevel'] != 6)
            $dt = \DateTime::createFromFormat('d.m.Y', $_REQUEST['ddata']);

        $data = array(
            'edu_level' => $_REQUEST['edulevel'],
            'almamatter' => trim($_REQUEST['almamatter']),
            'series' => ((int)$_REQUEST['edulevel'] != 6 ? trim($_REQUEST['dseries']) : null),
            'number' => ((int)$_REQUEST['edulevel'] != 6 ? trim($_REQUEST['dnumber']) : null),
            'regnumber' => ((int)$_REQUEST['edulevel'] != 6 ? trim($_REQUEST['regnumber']) : null),
            'datedoc' => ((int)$_REQUEST['edulevel'] != 6 ? $dt->format('Y-m-d') : null),
            'qualification' => $_REQUEST['qualification'],
            'stepen' => ($_REQUEST['stepen'] != '' ? $_REQUEST['stepen'] : null),
            'zvanie' => ($_REQUEST['zvanie'] != '' ? $_REQUEST['zvanie'] : null),
            'f' => ($_REQUEST['dlastname'] == '' ? NULL : $_REQUEST['dlastname']),
            'i' => ($_REQUEST['dfirstname'] == '' ? NULL : $_REQUEST['dfirstname']),
            'o' => ($_REQUEST['dfathername'] == '' ? NULL : $_REQUEST['dfathername']),
            'parent' => $insertId
        );
        $insertIddiplom = \QB::table('diplom')->insert($data);
        Error::pdump('Пишу диплом в базу, id - ' . $insertIddiplom);
        if ($_FILES['dscan']['name'] != '') {
            $extfile = substr(strrchr($_FILES['dscan']['name'], '.'), 1);
            $uploadfile = uploaddir . 'diplom_' . $insertIddiplom . '.' . $extfile;
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
        }
        if ($_FILES['fscan']['name'] != '') {
            $extfile = substr(strrchr($_FILES['fscan']['name'], '.'), 1);
            $uploadfile = uploaddir . 'fam_' . $insertIddiplom . '.' . $extfile;
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
        }

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
        Error::pdump('Пишу данные о работе в базу, id - ' . $insertIdwork);


        $this->sendActivationMail($_REQUEST['email']);
        return true;
    }

    /** =================================================================================================================================================
     * Высылаем письмо с активационным кодом для подтверждения регистрации
     * @param String $email
     */
    public function sendActivationMail($email)
    {
        $body = '<p>Здравствуйте!<br />Ваш e-mail был указан при регистрации личного кабинета на сайте Педагог 13.ру.</p>' .
            '<p>Если вы этого не делали, просто проигнорируйте это письмо.</p>' .
            'Если же заявку на регистрацию подавали вы, то <a href="' . SITE . '/?act=activation&id=' .
            urlencode($this->encrypt($email)) . '">перейдите по ссылке</a> для подтверждения регистрации.</p>'
            . '<p>Если у вас нет возможности перейти по ссылке, можете скопировать ссылку ниже и вставить ее в браузер для подтверждения аккаунта:<br />'
            . '' . SITE . '/?act=activation&id=' . urlencode($this->encrypt($email)) . '</p>'
            . '<p></p><p>Это письмо сгенерировано автоматически, отвечать на него не нужно - письмо ни до кого не дойдет!</p>';;

        $this->sendMail($email,
            'Подтверждение регистрации на сайте Педагог 13.ру',
            $body,
            'Вам выслано письмо с подтверждением регистрации.<br />Пожалуйста, проверьте свою почту.');
        $this->addRedirect(SITE . '/?act=loginform', 10);
    }

    /** ===================================================================================================================
     * Сохраняет данные пользователя
     * @return boolean
     */
    public function saveUser($moder = false)
    {
        if (!$this->isAuthorized()) {
            $this->setError('Вы не авторизованы! Сначала <a href="/?act=loginform">войдите под своей учетной записью</a>!');
            return false;
        }
        if (isset($_REQUEST['changepass']))
            if ($_REQUEST['pass1'] <> $_REQUEST['pass2']) {
                $this->setError('Введеные пароли не совпадают!');
                return false;
            }

        $err = '';
        if (!validateSnils($_REQUEST['snils'], $err)) {
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
            'birthday' => $dt->format('Y-m-d'),
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
        $uploadfile = uploaddir . 'snils_' . (int)$_REQUEST['id'] . '.' . $extfile;
        if ($_FILES['sscan']['tmp_name'] != '') {
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
        if (!$new) {
            $insertId = \QB::table('users')
                ->where('id', $moder ? filter_var($_REQUEST['id'], FILTER_SANITIZE_NUMBER_INT) : $this->user->id)
                ->update($data);
        } else {
            $insertId = \QB::table('users')->insert($data);
        }

        Error::pdump($insertId, 'Результат записи в базу');

        if (is_null($insertId)) {
            $this->setError('Ошибка записи данных пользователя в базу!');
            return false;
        }

        if (!$moder) $this->addContent('Изменения сохранены');

        //$this->addHeader('<meta http-equiv="refresh" content="3; URL='.SITE.'/?act=profile" />');
        $this->addRedirect(($moder ? $_SERVER['HTTP_REFERER'] . '&referer=' . $_REQUEST['referer'] : SITE . '/?act=profile'), ($moder ? 0 : 5));
        return true;
    }

    /** ===================================================================================================================
     * Сохраняет данные паспорта пользователя
     * @return boolean
     */
    public function savePassport($moder = false)
    {
        if (!$this->isAuthorized()) {
            $this->setError('Вы не авторизованы! Сначала <a href="/?act=loginform">войдите под своей учетной записью</a>!');
            return false;
        }
        $data = ($_REQUEST['pdata'] == '' ? date('d.n.Y') : $_REQUEST['pdata']);
        $dt = \DateTime::createFromFormat('d.m.Y', $data);

        $data = array(
            'citizenship' => $_REQUEST['citizenship'],
            'series' => trim($_REQUEST['pseries']),
            'number' => trim($_REQUEST['pnumber']),
            'datedoc' => ($_REQUEST['pdata'] == '' ? NULL : $dt->format('Y-m-d')),
            'info' => trim($_REQUEST['pvidan'])
        );

        Error::pdump($data, 'Данные для БД');
        $new = $_REQUEST['id'] == '';

        $extfile = null;
        if ($_FILES['pscan']['tmp_name'] != '') {
            $extfile = substr(strrchr($_FILES['pscan']['name'], '.'), 1);

            $uploadfile = uploaddir . 'pass_' . (int)$_REQUEST['id'] . '.' . $extfile;
            if (move_uploaded_file($_FILES['pscan']['tmp_name'], $uploadfile)) {
                $data['extfile'] = $extfile;
                Error::pdump('Скан паспорта загружен');
            } else {
                Error::pdump('Ошибка загрузки скана паспорта.');
                $this->setError('Скан паспорта загрузить не удалось.');
            }
        }

        if (!$new) {
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
                    'datedoc' => $dt->format('Y-m-d'),
                    'info' => trim($_REQUEST['pvidan']),
                    'parent' => $this->user->id,
                    'used' => 1
                ));
                Error::pdump($insertId, 'Результат записи в БД');
                return $insertId;
            });
            if ($_FILES['pscan']['tmp_name'] != '') {
                rename($uploadfile, uploaddir . 'pass_' . ($new ? $insertId : $_REQUEST['id']) . '.' . $extfile);
            }
        }

        if (is_null($insertId)) {
            $this->setError('Ошибка записи данных пользователя в базу!');
            return false;
        }

        if (!$moder) $this->addContent('Изменения сохранены');

        //$this->addHeader('<meta http-equiv="refresh" content="3; URL='.SITE.'/?act=profile" />');
        $this->addRedirect(($moder ? $_SERVER['HTTP_REFERER'] . '&referer=' . $_REQUEST['referer'] : SITE . '/?act=profile'), ($moder ? 0 : 5));
        return true;
    }

    /** ===================================================================================================================
     * Сохраняет данные о работе пользователя
     * @return boolean
     */
    public function saveWork($moder = false)
    {
        if (!$this->isAuthorized()) {
            $this->setError('Вы не авторизованы! Сначала <a href="/?act=loginform">войдите под своей учетной записью</a>!');
            return false;
        }

        $data = array(
            //'organisation' => str_replace('"', '&quot;', trim($_REQUEST['organisation'])),
            'organisation' => str_replace('"', '\";', trim($_REQUEST['organisation'])),
            'region' => trim($_REQUEST['wregion']),
            'distinctrm' => trim($_REQUEST['wregionrm']),
            'city' => trim($_REQUEST['wcity']),
            'profession' => trim($_REQUEST['dolgnost']),
            'stage' => trim($_REQUEST['wstage']),
            'gosslujba' => (isset($_REQUEST['gosslujba']) ? 1 : 0),
            'phone' => trim($_REQUEST['wphone']),
            'waddress' => trim($_REQUEST['waddress']),
            'checked' => 0
        );

        $new = $_REQUEST['id'] == '';
        if (!$new) {
            $insertId = \QB::table('work')->where('id', $_REQUEST['id'])->update($data);
        } else {
            $data['parent'] = $this->user->id;
            $insertId = \QB::table('work')->insert($data);
        }
        Error::pdump($insertId, 'Результат записи в БД');

        if (is_null($insertId)) {
            $this->setError('Ошибка записи данных пользователя в базу!');
            return false;
        }

        if (!$moder) $this->addContent('Изменения сохранены');

        //$this->addHeader('<meta http-equiv="refresh" content="3; URL='.SITE.'/?act=profile" />');
        //$this->addRedirect(($moder ? $_SERVER['HTTP_REFERER'].'&referer='.$_REQUEST['referer'] : SITE.'/?act=profile'), ($moder ? 0 : 5));
        return true;
    }

    /** ===================================================================================================
     * Блокирует\разблокирует курс для записи слушателей
     *
     */
    public function lockCourse($id = null)
    {
        if (!$this->isAuthorized()) {
            return false;
        }
        if (!$this->isRule(RULE_MODERATE)) {
            return false;
        }
        $query = \QB::table('course')->select('course.regclosed')->find($id);
        //Error::pdump($query);
        $data = array(
            'regclosed' => ($query->regclosed == 1 ? 0 : 1)
        );
        $insertId = \QB::table('course')->where('id', $id)->where('owner', $this->user->id)->update($data);
        $html = '<i class="fa-light fa-lock' . ($query->regclosed == 1 ? '-open' : '') . '" title="' . ($query->regclosed == 1 ? 'За' : 'От') . 'крыть для записи курс"></i>';
        $this->addContent($html);
        return true;
    }

    /** ===================================================================================================
     * Отправляет курс в архив
     *
     */
    public function inArch($id = null)
    {
        if (!$this->isAuthorized()) {
            return false;
        }
        if (!$this->isRule(RULE_MODERATE)) {
            return false;
        }
        $query = \QB::table('course')->select('course.archived')->find($id);
        Error::pdump($query);
        $data = array(
            'archived' => ($query->archived == 1 ? 0 : 1)
        );
        $insertId = \QB::table('course')->where('id', $id)->where('owner', $this->user->id)->update($data);
        Error::pdump($_SERVER);
        $html = '<script>location.href = "' . $_SERVER['HTTP_REFERER'] . '";</script>';
        $this->addContent($html);
        return true;
    }

    /** ===================================================================================================================
     * Сохраняет данные о дипломе пользователя
     * @return boolean
     */
    public function saveDiplom($moder = false)
    {
        if (!$this->isAuthorized()) {
            $this->setError('Вы не авторизованы! Сначала <a href="/?act=loginform">войдите под своей учетной записью</a>!');
            return false;
        }

        if ((int)$_REQUEST['edulevel'] != 6)
            $dt = \DateTime::createFromFormat('d.m.Y', $_REQUEST['ddata']);

        $data = array(
            'edu_level' => $_REQUEST['edulevel'],
            'almamatter' => trim($_REQUEST['almamatter']),
            'series' => ((int)$_REQUEST['edulevel'] == 6 ? null : trim($_REQUEST['dseries'])),
            'number' => ((int)$_REQUEST['edulevel'] == 6 ? null : trim($_REQUEST['dnumber'])),
            'regnumber' => ((int)$_REQUEST['edulevel'] == 6 ? null : trim($_REQUEST['regnumber'])),
            'datedoc' => ((int)$_REQUEST['edulevel'] == 6 ? null : $dt->format('Y-m-d')),
            'qualification' => trim($_REQUEST['qualification']),
            'stepen' => ($_REQUEST['stepen'] == '' ? NULL : trim($_REQUEST['stepen'])),
            'zvanie' => ($_REQUEST['zvanie'] == '' ? NULL : trim($_REQUEST['zvanie'])),
            'f' => ($_REQUEST['dlastname'] == '' ? NULL : trim($_REQUEST['dlastname'])),
            'i' => ($_REQUEST['dfirstname'] == '' ? NULL : trim($_REQUEST['dfirstname'])),
            'o' => ($_REQUEST['dfathername'] == '' ? NULL : trim($_REQUEST['dfathername']))
        );
        $extfile = null;
        if ($_FILES['dscan']['tmp_name'] != '') {
            $extfile = substr(strrchr($_FILES['dscan']['name'], '.'), 1);
            $duploadfile = uploaddir . 'diplom_' . (int)$_REQUEST['id'] . '.' . $extfile;
            if (move_uploaded_file($_FILES['dscan']['tmp_name'], $duploadfile)) {
                $data['dextfile'] = $extfile;
                Error::pdump('Скан диплома загружен');
            } else {
                Error::pdump('Ошибка загрузки скана диплома.');
                $this->setError('Скан диплома загрузить не удалось.');
            }
        }

        $extfile = null;
        if ($_FILES['fscan']['tmp_name'] != '') {
            $extfile = substr(strrchr($_FILES['fscan']['name'], '.'), 1);

            $fuploadfile = uploaddir . 'fio_' . (int)$_REQUEST['id'] . '.' . $extfile;
            if (move_uploaded_file($_FILES['fscan']['tmp_name'], $fuploadfile)) {
                $data['fextfile'] = $extfile;
                Error::pdump('Скан документа о смене ФИО загружен');
            } else {
                Error::pdump('Ошибка загрузки скана документа о смене ФИО.');
                $this->setError('Скан документа о смене ФИО загрузить не удалось.');
            }
        }

        $new = $_REQUEST['id'] == '';
        if (!$new) {
            $insertId = \QB::table('diplom')->where('id', $_REQUEST['id'])->update($data);
        } else {
            $data['parent'] = $this->user->id;
            $insertId = \QB::table('diplom')->insert($data);
            if ($_FILES['dscan']['tmp_name'] != '') {
                rename($duploadfile, uploaddir . 'diplom_' . $insertId . '.' . $extfile);
            }
            if ($_FILES['fscan']['tmp_name'] != '') {
                rename($fuploadfile, uploaddir . 'fio_' . $insertId . '.' . $extfile);
            }
        }

        Error::pdump($new, 'Новый диплом или правим старый');
        Error::pdump($insertId, 'Результат записи в БД');

        if (is_null($insertId)) {
            $this->setError('Ошибка записи данных пользователя в базу!');
            return false;
        }

        if (!$moder) $this->addContent('Изменения сохранены');

        //$this->addHeader('<meta http-equiv="refresh" content="3; URL='.SITE.'/?act=profile" />');
        $this->addRedirect(($moder ? $_SERVER['HTTP_REFERER'] . '&referer=' . $_REQUEST['referer'] : SITE . '/?act=profile'), ($moder ? 0 : 5));
        return true;
    }


    /** ===========================================================================================================================
     * Функция авторизации пользователя
     * @param Int $id - ID пользователя
     * @param String $pass - пароль
     * @return boolean - true если авторизация успешна
     */
    public function auth($id = null, $pass = null)
    {
        if (!is_null($id) && !is_null($pass)) {
            $login = 'id';
        } elseif (isset($_REQUEST['ep'])) {
            $login = 'email';
        } else {
            $login = 'phone';
        }
        $query = \QB::table('users')->select(array(
            'users.id',
            'users.snils',
            'users.extfile',
            'users.group',
            'users.rules',
            'users.added',
            'users.closed',
            'users.firstname',
            'users.lastname',
            'users.fathername',
            'users.sex',
            \QB::raw("DATE_FORMAT(" . \OnlineRecord\config['prefix'] . "users.birthday, '%d.%m.%Y') AS birthday"),
            'users.phone',
            'users.email',
            'users.pedstage',
            'users.password',
            'users.region',
            'users.city',
            'users.distinct',
            'users.address',
            'users.distinctrm',
            'users.cityrm',
            'users.checked',
            'users.activated'))
            ->where($login, '=', (isset($_REQUEST['eort']) ? $_REQUEST['eort'] : $id))
            ->where('activated', '=', 1)
            ->where('banned', '=', 0);

        $result = $query->first();
        //Error::pdump('auth');
        //Error::pdump($result);
        //Error::pdump($query->getQuery()->getRawSql());
        if (is_null($result)) {
            $this->setError('Неверные данные для входа или такого пользователя не существует! Или, возможно, вы не подтвердили свою учетную запись?<br>'
                . 'Не пришло письмо активации учетной записи? Мы можем <a class="button open_modal" href="#modal1">выслать его снова</a>.');
            Error::pdump('Авторизация не прошла');
            $html = '<form action="/" method="post" enctype="multipart/form-data"><input type="hidden" name="act" value="resendactivationmail"><label>Введите свой e-mail:<br><input type="text" name="email" style="width: 98%;"><br /><input type="submit" value="Отправить"></label></form>';
            $this->addModalContent($html);
            return false;
        }

        /*if (!is_null($result->extfile))
            $this->files[] = 'snils_'.$result->id.'.'.$result->extfile;*/

        if (!is_null($id) && !is_null($pass)) {
            if ($pass == $result->password) {
                $this->authorized = true;
                $this->user = $result;
                Error::pdump('Успешная авторизация из куки', 'Auth');
                return true;
            }
        }
        if (isset($_REQUEST['pass'])) {
            if (password_verify($_REQUEST['pass'], $result->password)) {
                $this->authorized = true;
                $this->user = $result;
                $c = ['user' => $this->user->id,
                    'pass' => $this->user->password];

                setcookie('onlineRecord', $this->encrypt(serialize($c)), time() + 7776000, '/', $_SERVER['SERVER_NAME']);
                Error::pdump('Успешная авторизация');

                //$this->addHeader('<meta http-equiv="refresh" content="0; URL='.SITE.($_REQUEST['courseid'] != '' ? '/?act=showmore&id='.$_REQUEST['courseid'] : '').'" />');

                $this->addRedirect(SITE . (isset($_REQUEST['cat']) ? '/?act=show&cat=' . $_REQUEST['cat'] : ''), 0);
                return true;
            } else {
                $this->setError('Неверные данные для входа или такого пользователя не существует! Или, возможно, вы не подтвердили свою учетную запись?<br>'
                    . 'Не пришло письмо активации учетной записи? Мы можем <a class="button open_modal" href="#modal1">выслать его снова</a>.');
                Error::pdump('Авторизация не прошла');
                $html = '<form action="/" method="post" enctype="multipart/form-data"><input type="hidden" name="act" value="resendactivationmail"><label>Введите свой e-mail:<br><input type="text" name="email" style="width: 98%;"><br /><input type="submit" value="Отправить"></label></form>';
                $this->addModalContent($html);
                return false;
            }
        } else {
            return false;
        }
    }


    /** ========================================================================================================================================
     * Функция подтверждения учетной записи через почту
     * @param string $actCode - код активации
     */
    public function activation($actCode)
    {
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
        //$this->addHeader('<meta http-equiv="refresh" content="5; URL='.SITE.'" /?act=loginform>');
        $this->addRedirect(SITE . '/?act=loginform', 5);
    }

    /** активация пользователя модератором
     * @param int $id - id пользователя
     * @param int $act - 1|0 - активировать/деактивировать
     * @return void
     */
    public function moderActivation(int $id, int $act)
    {
        if (!$this->isAuthorized()) {
            return;
        }
        if (!$this->isRule(RULE_MODERATE)) {
            return;
        }
        $data = array(
            'activated' => $act
        );
        $query = \QB::table('users')->where('id', $id)->where('banned', 0)->update($data);
    }

    /** бан/разбан пользователя
     * @param int $id - id пользователя
     * @param int $act - 1|0 - забанить/раззбанить
     * @return false|void
     */
    public function moderBan(int $id, int $act)
    {
        if (!$this->isAuthorized()) {
            return;
        }
        if (!$this->isRule(RULE_ADMIN)) {
            return;
        }
        $data = array(
            'banned' => $act
        );
        $query = \QB::table('users')->where('id', $id)->update($data);
    }

    /** ========================================================================================================================================
     * Функция, высылающая письмо восстановления пароля
     * @return boolean
     */
    public function lostPass()
    {
        $err = '';
        if (!validateSnils($_REQUEST['snils'], $err)) {
            $this->setError($err);
            return false;
        }

        $query = \QB::table('users')
            ->where('snils', '=', trim($_REQUEST['snils']))
            ->where('phone', '=', trim($_REQUEST['phone']))
            ->where('email', '=', trim($_REQUEST['email']));
        $result = $query->first();
        $count = $query->count();

        if ($count == 0) {
            $this->setError('Пользователь с такими данными (СНИЛС, телефон или e-mail) не существует! Вы можете <a href="/?act=regform">создать новую учетную запись</a> на сайте.');
            return false;
        } else {
            $body = 'Здравствуйте.<br />Мы получили запрос на восстановление пароля доступа к записи на курсы. '
                . 'Если вы не посылали такой запрос, то просто проигнорируйте это письмо.<br />' .
                'Если же это вы оставили запрос, то для смены пароля <a href="' . SITE . '/?act=resetpassform&id='
                . urlencode($this->encrypt($result->snils)) . '">перейдите по ссылке.</a>'
                . '<p></p><p>Это письмо сгенерировано автоматически, отвечать на него не нужно - письмо ни до кого не дойдет!</p>';;
            $this->sendMail($_REQUEST['email'],
                'Восстановление пароля на сайте Педагог13.ру',
                $body,
                'Проверьте свой почтовый ящик. На него должно прийти письмо со ссылкой на сброс пароля. '
                . 'Это может занять некоторое время. Если письмо не пришло, то возможно оно попало в спам.');
        }
        return true;
    }


    /** ========================================================================================================================================
     * Функция выводящая форму восстановления пароля
     * @return boolean
     */
    public function resetPassForm()
    {
        $query = \QB::table('users')->where('snils', '=', urldecode($this->decrypt($_REQUEST['id'])));
        $result = $query->first();
        $count = $query->count();

        if ($count == 0) {
            $this->setError('Пользователь не найден.');
            return false;
        } else {
            $this->addContent(file_get_contents(dirname(__FILE__) . '/resetpassForm.html'));
            $this->addJQCode(file_get_contents(dirname(__FILE__) . '/resetpassForm.jqs'));
            $this->addRtemplate('{%ID%}', $_REQUEST['id']);
            $this->addRtemplate('{%USERID%}', $_REQUEST['id']);
            return true;
        }
    }


    /** ========================================================================================================================================
     * Функция установки нового пароля после сброса пароля
     * @return boolean
     */
    public function resetPass()
    {

        if ($_REQUEST['pass1'] != $_REQUEST['pass2']) {
            $this->setError('Пароли не совпадают!');
            return false;
        }
        $query = \QB::table('users')->where('snils', '=', urldecode($this->decrypt($_REQUEST['id'])));
        $result = $query->first();
        $count = $query->count();

        if ($count == 0) {
            $this->setError('Пользователь не найден.');
            return false;
        } else {

            $data = array(
                'password' => password_hash($_REQUEST['pass1'], PASSWORD_BCRYPT),
                'rpass' => $_REQUEST['pass1']
            );
            $query = \QB::table('users')->where('snils', '=', urldecode($this->decrypt($_REQUEST['id'])))->update($data);
            if (!$query)
                return false;
            else
                return true;
        }
    }


    /** ========================================================================================================================================
     * Функция возвращает список регионов в теге <select>
     * @param Int $sel - выбранный пункт по умолчанию
     * @return string
     */
    public function getRegions($sel = null, $work = false)
    {
        $query = \QB::table('regions')->select('*')->orderBy('region');
        $result = $query->get();
        Error::pdump($work, 'Регионы');
        //Error::pdump($result);

        $html = '<select name="' . ($work ? 'w' : '') . 'region" id="' . ($work ? 'w' : '') . 'region" class="js-select2 s2">';

        foreach ($result as $val) {
            if (is_null($sel)) {
                $html .= '<option value="' . $val->id . '" ' . ($val->id == 13 ? ' selected="selected"' : '') . '>' . $val->region . '</option>';
            } else {
                $html .= '<option value="' . $val->id . '" ' . ($val->id == $sel ? ' selected="selected"' : '') . '>' . $val->region . '</option>';
            }
        }
        $html .= '</select>';
        return $html;
    }

    /** ========================================================================================================================================
     * Функция возвращает список источников финансирования в теге <select>
     * @param Int $sel - выбранный пункт по умолчанию
     * @return string
     */
    public function getFinanceSoursce($sel = null)
    {
        $query = \QB::table('finsource')->select('*')->orderBy('id');
        $result = $query->get();
        $html = '<select name="finance" id="finance">';

        foreach ($result as $val) {
            if (is_null($sel)) {
                $html .= '<option value="' . $val->id . '" >' . $val->source . '</option>';
            } else {
                $html .= '<option value="' . $val->id . '" ' . ($val->id == $sel ? ' selected="selected"' : '') . '>' . $val->source . '</option>';
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
    public function getPredmets($sel = null)
    {
        $query = \QB::table('predmet')->select('*')->orderBy('predmet');
        $result = $query->get();
        $html = '<select class="js-select2" name="predmet" id="predmet">';

        foreach ($result as $val) {
            if (is_null($sel)) {
                $html .= '<option value="' . $val->id . '" >' . $val->predmet . '</option>';
            } else {
                $html .= '<option value="' . $val->id . '" ' . ($val->id == $sel ? ' selected="selected"' : '') . '>' . $val->predmet . '</option>';
            }
        }
        $html .= '</select>';
        return $html;
    }

    /** ========================================================================================================================================
     * Функция возвращает список доп. проф. программ в теге <select>
     * @param Int $sel - выбранный пункт по умолчанию
     * @return string
     */
    public function getDpp($sel = null)
    {
        $query = \QB::table('dpp')->select('*')->orderBy('id');
        $result = $query->get();
        $html = '<select name="dpp" id="dpp">';

        foreach ($result as $val) {
            if (is_null($sel)) {
                $html .= '<option value="' . $val->id . '" >' . $val->dpp . '</option>';
            } else {
                $html .= '<option value="' . $val->id . '" ' . ($val->id == $sel ? ' selected="selected"' : '') . '>' . $val->dpp . '</option>';
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
    public function getAppType($sel = null)
    {
        $query = \QB::table('appstatus')->select('*')->orderBy('id');
        $result = $query->get();
        $html = '<select name="apptype" id="apptype"><option value="0" >Все заявки</option>';

        foreach ($result as $val) {
            if (is_null($sel)) {
                $html .= '<option value="' . $val->id . '" >' . $val->status . '</option>';
            } else {
                $html .= '<option value="' . $val->id . '" ' . ($val->id == $sel ? ' selected="selected"' : '') . '>' . $val->status . '</option>';
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
    public function getModes($sel = null)
    {
        $query = \QB::table('cmode')->select('*')->orderBy('id');
        $result = $query->get();
        $html = '<select name="mode" id="mode">';

        foreach ($result as $val) {
            if (is_null($sel)) {
                $html .= '<option value="' . $val->id . '" >' . $val->mode . '</option>';
            } else {
                $html .= '<option value="' . $val->id . '" ' . ($val->id == $sel ? ' selected="selected"' : '') . '>' . $val->mode . '</option>';
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
    public function getForms($sel = null)
    {
        $query = \QB::table('form')->select('*')->orderBy('id');
        $result = $query->get();
        $html = '<select name="form" id="form">';

        foreach ($result as $val) {
            if (is_null($sel)) {
                $html .= '<option value="' . $val->id . '" >' . $val->form . '</option>';
            } else {
                $html .= '<option value="' . $val->id . '" ' . ($val->id == $sel ? ' selected="selected"' : '') . '>' . $val->form . '</option>';
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
    public function getEdulevel($sel = null)
    {
        $query = \QB::table('education_level')->select('*')->orderBy('id');
        $result = $query->get();
        //Error::pdump('Уровни образования');
        //Error::pdump($result);

        $html = '<select name="edulevel" id="edulevel">';

        foreach ($result as $val) {
            if (is_null($sel)) {
                $html .= '<option value="' . $val->id . '">' . $val->level . '</option>';
            } else {
                $html .= '<option value="' . $val->id . '" ' . ($val->id == $sel ? ' selected="selected"' : '') . '>' . $val->level . '</option>';
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
    public function getCountries($sel = null)
    {
        $query = \QB::table('citizenship')->select('*')->orderBy('citizenship');
        $result = $query->get();
        //Error::pdump('Страны: ');
        //Error::pdump($result);
        $html = '<select name="citizenship" id="citizenship">';
        foreach ($result as $val) {
            if (is_null($sel)) {
                $html .= '<option value="' . $val->id . '" ' . ($val->id == 1 ? ' selected="selected"' : '') . ' >' . $val->citizenship . '</option>';
            } else {
                $html .= '<option value="' . $val->id . '" ' . ($val->id == $sel ? ' selected="selected"' : '') . ' >' . $val->citizenship . '</option>';
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
    public function getSex($sel = null)
    {
        $query = \QB::table('sex')->select('*')->orderBy('id');
        $result = $query->get();
        // Error::pdump('Пол: ');
        // Error::pdump($result);

        $html = '<select name="sex"  id="sex">';
        foreach ($result as $val) {
            if (is_null($sel))
                $html .= '<option value="' . $val->id . '" >' . $val->sex . '</option>';
            else
                $html .= '<option value="' . $val->id . '" ' . ($val->id == $sel ? ' selected="selected"' : '') . '>' . $val->sex . '</option>';
        }
        $html .= '</select>';
        return $html;
    }

    /** ========================================================================================================================================
     * Функция возвращает список районов РМ в теге <select>
     * @param Int $sel - выбранный пункт по умолчанию
     * @return string
     */
    public function getDistinct($sel = null, $work = false)
    {
        $query = \QB::table('distinct')->select('*')->orderBy('distinct');
        $result = $query->get();
        Error::pdump($result, 'Районы РМ: ');
        $html = '<select name="' . ($work ? 'w' : '') . 'regionrm" id="' . ($work ? 'w' : '') . 'regionrm" class="select2">';
        //$html .= '<option value="0" selected="selected">=== Выберите район ===</option>';
        foreach ($result as $val) {
            /*if (is_null($sel))
                $html .= '<option value="'.$val->id.'" >'.$val->distinct.'</option>';
            else*/
            $html .= '<option value="' . $val->id . '" ' . ($val->id == $sel ? ' selected="selected"' : '') . ' ' . (!is_null($val->city) ? 'data-city="' . $val->city . '"' : '') . '>' . $val->distinct . '</option>';
        }
        $html .= '</select>';
        return $html;
    }


    /** ========================================================================================================================================
     * Выводит заявки пользователя
     * @param type $type - статус заявок для выбора [0..6]
     */
    public function getUserApp($type = null)
    {

        $query = \QB::table('applications')->select(
            \QB::raw(\OnlineRecord\config['prefix'] . 'applications.id as appId'),
            'applications.course',
            'applications.diplom',
            'applications.work',
            \QB::raw("DATE_FORMAT(" . \OnlineRecord\config['prefix'] . "applications.regdate,'%d.%m.%Y %H:%i') as regdate"),
            'applications.status',
            \QB::raw(\OnlineRecord\config['prefix'] . 'appstatus.status as statusR'),
            'applications.group',
            'applications.status_changed',
            'course.name',
            'course.owner',
            \QB::raw("DATE_FORMAT(" . \OnlineRecord\config['prefix'] . "course.start,'%d.%m.%Y') as start"),
            \QB::raw("DATE_FORMAT(" . \OnlineRecord\config['prefix'] . "course.stop,'%d.%m.%Y') as stop"),
            'course.hours'
        )
            ->leftJoin('course', 'course.id', '=', 'applications.course')
            ->leftJoin('appstatus', 'appstatus.id', '=', 'applications.status')
            ->limit(Settings::getSetting('limitAppList'))
            ->where('applications.student', $this->user->id)
            ->orderBy('appId');

        if (!is_null($type) && $type != 0)
            $query->where('applications.status', $type);

        $result = $query->get();
        $count = $query->count();
        Error::pdump($count, 'count');
        Error::pdump($result, 'res');

        $this->addContent(file_get_contents(dirname(__FILE__) . '/showCourse.html'));
        $this->addRtemplate('{%APP TYPE%}', $this->getAppType((isset($_REQUEST['apptype']) && $_REQUEST['apptype'] != 0 ? filter_var($_REQUEST['apptype'], FILTER_SANITIZE_NUMBER_INT) : $type)));
        $this->setTitle('Список моих заявок');

        if ($count != 0) {
            $html = '';
            foreach ($result as $v) {
                $html .= '<div class="formrow shadow round5border dgraybg margin5updown message-block ' . ($v->status_changed == 1 ? "new-status" : "") . '"><div class="appststus">Статус заявки: ' . $v->statusR . '</div>'
                    . '<div class="coursename">Курс: '
                    . '&laquo;<a href="#modal1" class="open_modal" data-id="' . $v->course . '" data-diplom="' . $v->diplom . '" data-work="' . $v->work . '">'
                    . $v->name . '</a>&raquo; ' . $v->hours . ' ч. с&nbsp;' . $v->start . '&nbsp;по&nbsp;' . $v->stop
                    . '</div>'
                    . '<div class="appdesc"></div>'
                    . '</div>';
            }
            $this->addContent('<p class="tcenter bold" style="">Мои заявки' . (!is_null($type) ? ' в статусе &laquo;' . $v->statusR . '&raquo;' : '') . '</p><div class="tcenter" id="apptypeblock">{%APP TYPE%}</div>');
            $this->addContent($html);
        } else {
            $this->addContent('<p class="tcenter bold">Заявки с таким статусом отсутсвуют...</p><div class="tcenter" id="apptypeblock">{%APP TYPE%}</div>');
        }
        $js = '$(".open_modal").on("click", function(){ '
            . '$(".modalcontent").load("/?act=showcourse&id=" + $(this).data("id"), { hidectrl : 1, diplom: $(this).data("diplom"), work: $(this).data("work")}); '
            . '}); '
            . '$("#apptype").on("change", function(){ location.href="/?act=applications&apptype=" + $(this).val(); }); ';
        $this->addJQCode($js);

        $data = array('status_changed' => 0);
        \QB::table('applications')->where('applications.student', $this->user->id)->update($data);
    }

    /** ========================================================================================================================================
     * Выводит заявки пользователя или модератора
     * @param int $type - статус заявок для выбора [0..6]
     * @param int $user - id пользователя, чьи заявки показывать (толко для APP_MODE_STUDENT)
     * @param int $course - id курса, заявки на который показывать (толко для APP_MODE_MODERATOR)
     */
    public function getModeratorApp($type = 1, $user = null, $course = null, $pageN = 1)
    {
        if (!$this->isAuthorized()) {
            $this->setError('Вы не авторизованы! Сначала <a href="/?act=loginform">войдите под своей учетной записью</a>!');
            return false;
        }
        $this->addContent('<p class="tcenter bold">Модерация заявок на мои курсы</p>');
        $where = '';

        if (!is_null($type) && $type != 0)
            $where .= (strlen($where) != 0 ? ' AND ' : ' WHERE ') . ' a.status = ' . $type;

        if (is_null($user)) {
            $where .= (strlen($where) != 0 ? ' AND ' : ' WHERE ') . ' c.owner = ' . $this->user->id;
        } else {
            $where .= (strlen($where) != 0 ? ' AND ' : ' WHERE ') . ' c.owner = ' . $user;
        }

        if (!is_null($course))
            $where .= (strlen($where) != 0 ? ' AND ' : ' WHERE ') . 'a.course = ' . $course;

        Error::pdump($where, 'where');
        $sql = 'SELECT SQL_CALC_FOUND_ROWS
                    a.id  as appId,
                    a.course, 
                    a.student, 
                    a.passport, 
                    a.diplom, 
                    a.work, 
                    DATE_FORMAT(a.regdate,"%d.%m.%Y %H:%i") as regdate, 
                    a.status,
                    s.status as statusR,
                    a.group, 
                    a.status_changed, 
                    c.name, 
                    c.owner, 
                    DATE_FORMAT(c.start,"%d.%m.%Y") as start, 
                    DATE_FORMAT(c.stop,"%d.%m.%Y") as stop,  
                    c.hours,
                    u.id as userId, 
                    u.firstname, 
                    u.lastname, 
                    u.fathername,
                    u.sex,
                    sex.sex as sexR,
                    DATE_FORMAT(u.birthday,"%d.%m.%Y") as birthday,
                    u.fathername,
                    (DATE_FORMAT(FROM_DAYS(TO_DAYS(now()) - TO_DAYS(u.birthday)), "%Y") + 0) as age,
                    u.phone,
                    u.email,
                    u.pedstage,
                    u.city,
                    r.region,
                    rm.distinct,
                    w.organisation,
                    w.profession,
                    u.checked,
                    w.checked as workcheck,
                    p.checked as passcheck, 
                    d.checked as diplomcheck
              FROM uts_applications a      
                LEFT JOIN uts_course c ON c.id = a.course
                LEFT JOIN uts_appstatus s ON s.id = a.status
                LEFT JOIN uts_users u ON u.id = a.student
                LEFT JOIN uts_regions r ON r.id = u.region
                LEFT JOIN uts_distinct rm ON rm.id = u.distinctrm
                LEFT JOIN uts_work w ON w.id = a.work
                LEFT JOIN uts_pass p ON p.id = a.passport
                LEFT JOIN uts_sex sex ON sex.id = u.sex
                LEFT JOIN uts_diplom d ON d.id = a.diplom ' .
            $where .
            ' ORDER BY appId
              LIMIT ' . ($pageN - 1) * Settings::getSetting('limitAppList') . ", " . Settings::getSetting('limitAppList');
        Error::pdump($sql, 'SQL app');
        $query = \QB::query($sql);
        $result = $query->get();
        Error::pdump($result, 'result');
        $count = count($result);
        Error::pdump($count, 'count');
        Error::pdump($result, 'res');
        /*$queryObj = $query->getQuery();
        Error::pdump($queryObj->getRawSql(), 'SQL');*/

        $query = \QB::query("SELECT FOUND_ROWS() as `count`");
        $pages = $query->get()[0]->count;
        Error::pdump($pages, 'всего заявок');
        $pCount = (int)ceil($pages / Settings::getSetting('limitAppList'));
        Error::pdump($pCount, 'pCpount');
        $pager = '<span class="pttl">Страницы</span> <div class="pager">' . ($pageN == 1 ? '' : '<a href="/?act=moderate&apptype=' . $type . (!is_null($course) ? '&course=' . $course : '') . '&page=' . ($pageN - 1) . '"><span class="str" title="Предыдущая страница">&laquo;</span></a> ');
        for ($i = 1; $i <= $pCount; $i++) {
            $pager .= '<a href="/?act=moderate&apptype=' . $type . (!is_null($course) ? '&course=' . $course : '') . '&page=' . $i . '"><span class="str' . ($i == $pageN ? ' actpg' : '') . '">' . $i . '</span></a> ';
        }
        $pager .= ($pageN == $pCount ? '' : '<a href="/?act=moderate&apptype=' . $type . (!is_null($course) ? '&course=' . $course : '') . '&page=' . ($pageN + 1) . '"><span class="str" title="Следующая страница">&raquo;</span></a>') . '</div>';

        $this->addContent(file_get_contents(dirname(__FILE__) . '/showCourse.html'));
        $this->addRtemplate('{%COURSE%}', ($course ?? ''));
        $this->addRtemplate('{%APP TYPE%}', $this->getAppType((isset($_REQUEST['apptype']) && $_REQUEST['apptype'] != 0 ? filter_var($_REQUEST['apptype'], FILTER_SANITIZE_NUMBER_INT) : $type)));
        $this->setTitle('Список заявок');

        if ($count != 0) {
            $html = $pager;
            foreach ($result as $v) {
                $dataChecked = $v->checked != 0 && $v->passcheck != 0 && $v->workcheck != 0 && $v->diplomcheck != 0;
                Error::pdump($dataChecked, 'd Ch');
                $html .= '<div class="formrow shadow round5border dgraybg margin5updown message-block ">'//'.($v->status_changed == 1 ? "new-status" : "").'
                    . '<div class="appststus">Статус заявки: ' . $v->statusR . '. '
                    . '<span class="datacheck">'
                    . 'Данные: <img src="/img/' . ($v->checked == 0 ? 'bad' : 'good') . '.png" title="Данные пользователя ' . ($v->checked == 0 ? 'не ' : '') . 'проверены" class="show-tooltip">'
                    . '<img src="/img/' . ($v->passcheck == 0 ? 'bad' : 'good') . '.png" title="Данные о работе ' . ($v->passcheck == 0 ? 'не ' : '') . 'проверены" class="show-tooltip">'
                    . '<img src="/img/' . ($v->workcheck == 0 ? 'bad' : 'good') . '.png" title="Данные паспорта ' . ($v->workcheck == 0 ? 'не ' : '') . 'проверены" class="show-tooltip">'
                    . '<img src="/img/' . ($v->diplomcheck == 0 ? 'bad' : 'good') . '.png" title="Данные диплома ' . ($v->diplomcheck == 0 ? 'не ' : '') . 'проверены" class="show-tooltip">'
                    . '</span>'
                    . '</div>'

                    . '<div class="coursename">Курс: &laquo;<a href="#modal1" class="open_modal" data-id="' . $v->course . '">' . $v->name . '</a>&raquo; &ndash; '
                    . $v->hours . ' ч. с&nbsp;' . $v->start . '&nbsp;по&nbsp;' . $v->stop
                    . '</div>'

                    . '<div class="student">Заявитель: <span class="good">' . $v->lastname . ' ' . $v->firstname . ' ' . $v->fathername . '</span>'
                    . ' <i class="fa-light fa-' . ($v->sex == 1 ? 'mars' : 'venus') . ' show-tooltip" title="' . $v->sexR . '"></i>, '
                    . $v->profession . ' ' . $v->organisation . '. '
                    . '</div>'

                    . '<div class="more hidden">'
                    . $v->city . ', ' . (!is_null($v->distinct) ? $v->distinct . '. ' : '') . $v->region
                    . '. Стаж - ' . $v->pedstage . number($v->pedstage, array(' год', ' года', ' лет')) . '. Дата рождения - ' . $v->birthday . ' [' . $v->age . number($v->age, array(' год', ' года', ' лет')) . '].<br>'
                    . 'Контакты: <a href="tel:' . str_replace(array('(', ')', '-'), array('', '', ''), $v->phone) . '">' . $v->phone . '</a>, '
                    . '<a href="mailto:' . $v->email . '">' . $v->email . '</a>'
                    . '</div>'

                    . (($v->status == APP_STATUS_NEW || $v->status == APP_STATUS_ACCEPTED || $v->status == APP_STATUS_REJECTED) ?
                        '<div class="appctrl">'
                        . '<span>Заявку </span>'
                        . (($v->status == APP_STATUS_NEW || $v->status == APP_STATUS_REJECTED) && $dataChecked ?
                            '<a href="/?act=moderate&accept=1&appid=' . $v->appId . '&course=' . $v->course . '">'
                            . '<i class="fa-light fa-thumbs-up fa-2x good" title="Одобрить заявку"></i></a> ' : '')
                        . (($v->status == APP_STATUS_NEW || $v->status == APP_STATUS_ACCEPTED) ?
                            '<a href="/?act=moderate&reject=1&appid=' . $v->appId . '&course=' . $v->course . '">'
                            . '<i class="fa-light fa-thumbs-down fa-2x wrong" title="Отклонить заявку"></i></a> ' : '')
                        //. '<a href="/?act=moderate&revoke=1&appid='.$v->appId.'"><img src="/img/1x1.gif" class="icobtn32 ib32delete" title="Удалить заявку"></a> '
                        . '</div>' : '')

                    . (($v->checked == 1 && $v->workcheck == 1 && $v->passcheck == 1 && $v->diplomcheck == 1) ? '' :
                        '<div class="userctrl">'
                        . '<span>Проверить </span>'
                        . ($v->checked == 1 ? '' : '<a href="/?act=check&userdata=' . $v->student . '"><i class="fa-light fa-id-card fa-2x" title="данные пользователя" style="color: greenyellow;"></i></a> ')
                        . ($v->workcheck == 1 ? '' : '<a href="/?act=check&work=' . $v->work . '"><i class="fa-light fa-briefcase fa-2x" title="данные о работе" style="color: lightyellow;"></i></a> ')
                        . ($v->passcheck == 1 ? '' : '<a href="/?act=check&passport=' . $v->passport . '"><i class="fa-light fa-passport fa-2x" title="паспортные данные" style="color: sandybrown;"></i></a> ')
                        . ($v->diplomcheck == 1 ? '' : '<a href="/?act=check&diplom=' . $v->diplom . '"><i class="fa-light fa-file-certificate fa-2x" title="данные диплома" style="color: cornflowerblue;"></i></a> ')
                        . '</div>')

                    . '</div>';
            }
            $js = '$(".student").on("click", function(){ $(".more").hide(); $(this).next(".more").show();});';
            $this->addJQCode($js);
            $this->addContent('<div class="tcenter" id="apptypeblock">Показывать {%APP TYPE%}</div>');
            //$this->addContent('<p class="tcenter bold" style="">Заявки'.(!is_null($type) ? ' в статусе &laquo;'.$v->statusR.'&raquo;' : '').'</p><div class="tcenter" id="apptypeblock">{%APP TYPE%}</div>');
            $html .= $pager;
            $this->addContent($html);
        } else {
            $this->addContent('<p class="tcenter bold">Заявки с таким статусом отсутсвуют...</p><div class="tcenter" id="apptypeblock">{%APP TYPE%}</div>');
        }
        $js = '$(".open_modal").on("click", function(){ '
            //. '$("#modal1").css({"width": "98%", "margin-left": "-50%"});'
            . '$(".modalcontent").load("/?act=showcourse&id=" + $(this).data("id"), { hidectrl : 1}); '
            . '}); '
            . '$("#apptype").on("change", function(){ location.href="/?act=moderate&apptype=" + $(this).val() + ($("#course").val() != "" ? "&course="+$("#course").val() : ""); }); ';
        $this->addJQCode($js);
    }

    //========================================================================================================================================
    public function setAppState($appid, $state, $course = 0, $sendMail = false)
    {
        if (!$this->isAuthorized()) return false;
        Error::pdump('id-' . $appid . ' st-' . $state, 'SetAppState');
        Error::pdump($this, 'this');

        $data = array(
            'status' => $state,
            //'group' => $result->id,
            'status_changed' => 1
        );
        $query = \QB::table('applications')->select(array(
            \QB::raw(\OnlineRecord\config['prefix'] . "applications.id AS appID"),
            \QB::raw(\OnlineRecord\config['prefix'] . "applications.course AS courseID"),
            \QB::raw(\OnlineRecord\config['prefix'] . "applications.student AS studentID"),
            'course.name',
            'users.firstname',
            'users.email',
            'course.chatlink'
        ))
            ->leftJoin('course', 'course.id', '=', 'applications.course')
            ->leftJoin('users', 'users.id', '=', 'applications.student')
            ->where('applications.id', $appid)
            ->where('applications.course', '=', $course);
        $r = $query->first();
        Error::pdump($query->getQuery()->getRawSql(), 'заявка');
        Error::pdump($r, 'заявка');

        if ($state == APP_STATUS_ACCEPTED) {
            $query = \QB::table('coursegroup')->where('course', $course)->orderBy('id');
            $result = $query->first();
            Error::pdump($result, 'группа курса');
            $data['group'] = $result->id;
            $this->addMessage('Ваша заявка одобрена!',
                'Добрый день, ' . $r->firstname . '!<br>Ваша заявка на курс "<a href="#modal1" class="open_modal" ' .
                'data-id="' . $r->courseID . '">' . $r->name . '</a>' .
                '" одобрена руководителем курсов. <br>Вы можете <a href="/?act=printdoc&doctype=1&student=[%22' . $r->appID . '%22]">распечатать заявление о зачислении на курс</a>.<br>' .
                (!is_null($r->chatlink) ? 'Чат курса: <a href="' . $r->chatlink . '">' . $r->chatlink . '</a><br><br>' : ''),
                $r->studentID);
            $body = 'Добрый день, ' . $r->firstname . '!<br>Ваша заявка на курс "' . $r->name .
                '" одобрена руководителем курсов. <br>Вы можете ' .
                '<a href="' . SITE . '/?act=printdoc&doctype=1&student=[%22' . $r->appID . '%22]">распечатать заявление о зачислении на курс</a>. ' .
                'Также это можно сделать в своем личном кабинете в разделе "Мой профиль"->"Мои заявки на курсы".<br>' .
                'Перейти на <a href="' . SITE . '/?act=applications">наш сайт</a><br><br>' .
                (!is_null($r->chatlink) ? 'Чат курса:' . $r->chatlink . '<br><br>' : '') .
                'Спасибо, что вы с нами!'
                . '<p></p><p>Это письмо сгенерировано автоматически, отвечать на него не нужно - письмо ни до кого не дойдет!</p>';
            if ($sendMail)
                $this->sendMail($r->email, 'Ваша заявка на курс одобрена', $body, '');
        }
        if ($state == APP_STATUS_REJECTED) {
            $data['group'] = 0;
            $this->addMessage('Ваша заявка отклонена!',
                'Добрый день, ' . $r->firstname . '!<br>Ваша заявка на курс "<a href="#modal1" class="open_modal" ' .
                'data-id="' . $r->courseID . '">' . $r->name . '</a>' .
                '" отклонена руководителем курсов. <br>Возможно группы уже сформированы или вы предоставили не все требуемые документы.<br>' .
                'Для выяснения подробностей свяжитесь с руководителем курсов.<br>' . '<p></p><p>Это письмо сгенерировано автоматически, отвечать на него не нужно - письмо ни до кого не дойдет!</p>',
                $r->studentID);
            $body = 'Добрый день, ' . $r->firstname . '!<br>Ваша заявка на курс "' . $r->name .
                '" отклонена руководителем курсов. <br>Возможно группы уже сформированы или вы предоставили не все требуемые документы. ' .
                'Для выяснения подробностей свяжитесь с руководителем курсов.<br>' .
                'Для этого <a href="' . SITE . '/?act=applications">перейдите на наш сайт</a><br><br>' .
                'Спасибо, что вы с нами!'
                . '<p></p><p>Это письмо сгенерировано автоматически, отвечать на него не нужно - письмо ни до кого не дойдет!</p>';;
            $this->sendMail($r->email, 'Ваша заявка на курс одобрена', $body, '');

        }

        $insertId = \QB::table('applications')->where('id', $appid)->update($data);
    }

    //========================================================================================================================================
    public function isRule($rule)
    {
        if (!$this->isAuthorized()) return false;
        return ($rule & $this->user->rulesR) == $rule;
    }


    /** ========================================================================================================================================
     * Возвращает готовность всех документов
     * @param type $rtype - тип документов [ READY_ALL | READY_PASSPORT | READY_DIPLOM | READY_WORK ]
     * @return boolean
     */
    public function isReady($rtype)
    {
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
    public function getUserInfo()
    {
        if (!$this->isAuthorized()) {
            //$this->setError('Вы не авторизованы! Сначала <a href="/?act=loginform">войдите под своей учетной записью</a>!');
            return false;
        }

        $query = \QB::table('users')->select(array(
            'users.sex', 'users.region', 'users.distinctrm', 'sex.sex', 'regions.region', 'distinct.distinct',
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
            $this->files[] = 'snils_' . $result->id . '.' . $result->extfile;

        $this->user->sexR = $result->sex;
        $this->user->regionR = $result->region;
        $this->user->distinctrmR = $result->distinct;
        $this->user->groupR = $result->group;
        $this->user->rulesR = $result->rules | $this->user->rules;

        $query = \QB::table('messages')->where('messages.owner', $this->user->id)->orderBy('messages.sended', 'DESC');
        $result = $query->get();
        $this->messages = $result;
        if (!is_null($result)) {
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

        if ($this->isRule(RULE_MODERATE)) {
            $query = \QB::table('applications')->leftJoin('course', 'course.id', '=', 'applications.course')
                ->where('course.owner', $this->user->id)->where('applications.status', 1);
            $this->appModerCount = $query->count();
        }

        $query = \QB::table('pass')->select(array(
            'pass.id', \QB::raw(\OnlineRecord\config['prefix'] . 'pass.citizenship as citizen'), 'pass.series', 'pass.number',
            \QB::raw("DATE_FORMAT(" . \OnlineRecord\config['prefix'] . "pass.datedoc,'%d.%m.%Y') as datedoc"), 'pass.info',
            'pass.extfile', 'pass.parent', 'pass.checked', 'citizenship.citizenship'))
            ->leftJoin('citizenship', 'citizenship.id', '=', 'pass.citizenship')
            ->where('pass.parent', '=', $this->user->id);
        $result = $query->get();

        if ($query->count() != 0)
            $this->added['passport'] = true;

        $this->pass = $result;
        foreach ($result as $v) {
            if (!is_null($v->extfile))
                $this->files[] = 'pass_' . $v->id . '.' . $v->extfile;
        }
        //Error::pdump($result);

        $query = \QB::table('diplom')->select(array(
            'diplom.id', 'diplom.edu_level', 'diplom.almamatter', 'education_level.level', 'diplom.series', 'diplom.number', 'diplom.regnumber',
            \QB::raw("DATE_FORMAT(" . \OnlineRecord\config['prefix'] . "diplom.datedoc,'%d.%m.%Y') as datedoc"), 'diplom.qualification',
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
                $this->files[] = 'diplom_' . $v->id . '.' . $v->dextfile;
            if (!is_null($v->fextfile))
                $this->files[] = 'fio_' . $v->id . '.' . $v->fextfile;

        }
        $query = \QB::table('work')->select(
            \QB::raw(\OnlineRecord\config['prefix'] . 'work.id as workid'), 'work.parent', 'work.organisation', 'work.waddress', 'work.profession', 'work.stage',
            'work.region', \QB::raw(\OnlineRecord\config['prefix'] . 'regions.region as regionR'), 'work.distinctrm', \QB::raw(\OnlineRecord\config['prefix'] . 'distinct.distinct as distinctR'),
            'work.city', 'work.gosslujba', 'work.phone', 'work.checked'
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

    /** ========================================================================================================================================
     * Получает и выводит группы слушателей курса
     * @param Integer $sourse - id курса
     * @return boolean
     */
    public function getGroups($course, $group = 0)
    {
        if (!$this->isAuthorized()) {
            return false;
        }
        if (!$this->isRule(RULE_MODERATE)) {
            $this->setError('Вы не имеете доступа к этому функционалу! Обратитесь к администратору!');
            return false;
        }
        if ((int)$course == 0) {
            return false;
        }

        $html = '';
        $tabs = array();
        $divs = array();
        $students = array();
        //Error::pdump($tabs,'tab');

        // получаем  список групп ---------------------------------------------------------------------
        //$query = \QB::table('coursegroup')->where('coursegroup.course', $course);
        $query = \QB::query('SELECT g.*, c.name,'
            . '(SELECT COUNT(*) FROM ' . \OnlineRecord\config['prefix'] . 'applications a WHERE a.`group` = g.id) AS `count`'
            . ' FROM ' . \OnlineRecord\config['prefix'] . 'coursegroup g '
            . ' LEFT JOIN `' . \OnlineRecord\config['prefix'] . 'course` c ON `c`.`id` = ?'
            . ' WHERE g.course = ?', array($course, $course));
        $groups = $query->get();

        $this->setTitle('Группы курса &laquo;' . $groups[0]->name . '&raquo;');
        $this->addContent('<h3>Курс &laquo;' . $groups[0]->name . '&raquo;</h3>');

        //$groupCount = $query->count();
        $groupArray = array();
        Error::pdump($groups, 'группы');
        //Error::pdump($query->getQuery()->getSql(), 'sql');

        $html .= "<style>\n";
        //генерируем табы и открываем блоки контента вкладок
        foreach ($groups as $k => $v) {
            $students[$v->id] = 0;
            // формируем табы
            $tabs[$v->id] = '<input type="radio" class="itab" name="tab-btn" ' . (is_null($v->orderAddNumber) ? 'data-count="' . $v->count . '" data-gname="' . $v->groupname . '"' : '') . ' id="tab-btn-'
                . $v->id . '" value="' . $v->id . '"' . (($group == 0 && $k == 0) ? ' checked' : ($v->id == $group ? ' checked' : '')) . '><label for="tab-btn-' . $v->id . '">' . $v->groupname . '</label>';
            // формируем начало списка группы и управляющие элементы
            $divs[$v->id] = '<div id="content-' . $v->id . '"><div class="brorder-bottom"><label><input type="checkbox" class="selectall"> Выбрать всех</label>';
            if (is_null($v->orderAddNumber)) {
                $divs[$v->id] .= '<span class="groupctrl" style="background-color: #929ebc;">'
                    . '<a href="#modal2" title="Переименовать группу" class="editgroup open_modal show-tooltip"><i class="fa-light fa-pen-field"></i></a> '
                    . '<a href="/?act=deletegroup&id=' . $v->id . '" title="Удалить группу" class="delgroup show-tooltip"><i class="fa-light fa-trash-can"></i></a> '
                    . '</span>';
            }
            if (is_null($v->orderEndNumber)) {
                $divs[$v->id] .= '<span class="groupctrl">'
                    . (is_null($v->orderAddNumber) ? '<a title="Перевести слушателя в другую группу" class="crossgroup show-tooltip" style="cursor: pointer;">'
                        . '<i class="fa-light fa-arrow-right-arrow-left"></i><span class="selectgroup"></span></a> ' : '')
                    . (is_null($v->orderExpNumber) ? '<a title="Отчислить слушателя" class="expulse show-tooltip" style="cursor: pointer;">'
                        . '<i class="fa-light fa-user-slash"></i><span class="selectgroup"></span></a> ' : '')
                    . '</span>';
            }
            $divs[$v->id] .= '<span class="groupctrl">'
                . '<a class="printstudent" style="cursor: pointer;">'
                . '<i class="fa-light fa-print show-tooltip" title="Печать документов"></i>'
                . '<span class="printctrl">'
                . '<span class="printzop">Заявление о приеме</span>'
                . '<span class="printall">Список группы</span>'
                . ($this->isRule(RULE_DOCS) ?
                    '<span class="printpoz">Приказ о зачислении</span><span class="printpoo">Приказ об отчислении</span><span class="printpoe">Приказ об окончании</span><span class="printved">Ведомость/протокол</span>'
                    : '')
                . '</span>'
                . '</a> '
                . '</span>';
            $divs[$v->id] .= '</div>';
            // формируем css для вкладок
            $html .= ($k == 0 ? '' : ",\n") . "#tab-btn-" . $v->id . ":checked~#content-" . $v->id;
        }
        $html .= " {\n\tdisplay: block; \n}\n</style>\n";
        Error::pdump($tabs, 'группы');
        Error::pdump($html, 'html');

        //генерируем контент вкладок (списки групп) ---------------------------------------------------
        //'.\OnlineRecord\config['prefix'].'
        $query = 'SELECT *, '
            . '(SELECT CONCAT(lastname, " ", firstname, " ", fathername, " [", DATE_FORMAT(birthday,\'%d.%m.%Y\') ,"], ", city) '
            . 'FROM ' . \OnlineRecord\config['prefix'] . 'users '
            . 'WHERE ' . \OnlineRecord\config['prefix'] . 'users.id = ' . \OnlineRecord\config['prefix'] . 'applications.student) AS `user` '
            . 'FROM ' . \OnlineRecord\config['prefix'] . 'applications '
            . 'WHERE `course` = ' . $course . ' AND `status` NOT IN (' . APP_STATUS_REJECTED . ', ' . APP_STATUS_REVOKED . ') ORDER BY `user`';
        Error::pdump($query, 'sql apps');
        $query = \QB::query($query);
        //status NOT IN (".APP_STATUS_REJECTED.", ".APP_STATUS_REVOKED.", ".APP_STATUS_EXPULSION.")"
        $result = $query->get();

        //$studentCount = $query->count();
        Error::pdump($result, 'слушатели');

        //шерстим слушателей
        foreach ($result as $k => $v) {
            Error::pdump($v, 'v');
            if ($v->group != 0) {
                $divs[$v->group] .= '<span class="userlist' . ($v->status == APP_STATUS_EXPULSION ? ' wrong' : '') . '">'
                    . '<input type="checkbox" class="studentcheck" name="student[]" id="app-' . $v->id . '" value="' . $v->id . '"> <label for="app-' . $v->id . '">'
                    . $v->user . ($v->status == APP_STATUS_EXPULSION ? ' [Отчислен]' : '')
                    . '</label></span>';
                //$students[$v->group]+= ($v->status == APP_STATUS_EXPULSION ? 0 : 1);
                $students[$v->group]++;
            }
        }
        foreach ($divs as $k => $v) {
            $divs[$k] .= '<span class="stcount">В группе ' . $students[$k] . ' ' . number($students[$k], array('слушатель', 'слушателя', 'слушателей')) . '</span>';
        }


        // закрываем блоки контента ---------------------------------------------------------------------
        foreach ($divs as $k => $v) {
            $divs[$k] .= '</div>';
        }

        // начинаем собирать контент---------------------------------------------
        $html .= '<div class="tabs">';
        foreach ($tabs as $v)
            $html .= $v . "\n";
        $html .= '<label id="newgroup" title="Создать группу">'
            . '<a href="#modal1" class="open_modal" style="width: 100%; height: 100%; display: block;"><i class="fa-light fa-plus"></i></a>'
            . '</label>';
        foreach ($divs as $v)
            $html .= $v . "\n";
        $html .= '</div>';//<div class="tabs">
        // закончили генерить контент---------------------------------------------

        $this->addContent($html);
        $this->addJQCode(file_get_contents(dirname(__FILE__) . '/getGroups.js'));

        $mHtml = '<h4 style="text-align: center; margin: 0;margin-bottom: 5px;" id="modtitle">Создание новой группы.</h4>'
            . '<form id="addnewgroup" action="/?act=addnewgroup" enctype="multipart/form-data" method="post">'
            . '<input type="hidden" name="course" value="' . $course . '">'
            . '<input type="text" name="groupname" id="groupname" style="width: 98%; margin-bottom: 15px;">'
            . '<input type="submit" value="Создать группу" class="modal_ok">'
            . '</form></div></div>';
        $mHtml .= '<div id="modal2" class="modal_div">
                    <button class="modal_close" title="Закрыть окно">X</button>
                    <div class="modalcontent"><h4 style="text-align: center; margin: 0;margin-bottom: 5px;" id="modtitle">Введите новое имя группы</h4>
                    <form id="editgroup" action="/?act=editgroup" enctype="multipart/form-data" method="post">
                        <input type="hidden" value="" id="groupid" name="id">
                        <input type="text" name="grouprename" id="grouprename" style="width: 98%; margin-bottom: 15px;">
                        <input type="submit" id="grenamesubmit" value="Переименовать группу" class="modal_ok">
                        </form></div>
                </div>';
        $mHtml .= '<div id="modal3" class="modal_div">
                    <button class="modal_close" title="Закрыть окно">X</button>
                    <div class="modalcontent"><h4 style="text-align: center; margin: 0;margin-bottom: 5px;" id="modtitle">Введите данные приказа</h4>
                        <p><label for="date">Дата приказа </label><input type="text" name="date" id="date" value="" size="10">
                        <label for="pnumber" style="float: right;">Номер приказа </label><input type="text" name="pnumber" id="pnumber" size="10" style="float: right;"></p>
                        <div class="warn">Приказ уже был сформирован!</div>
                        <p align="center" id="rnblock"><input type="checkbox" name="save" id="save" checked="checked"><label for="save"> Сохранить данные приказа</label></p>
                        <button id="pozprint" class="modal_ok">Скачать приказ</button>
                        </div>
                </div>';
        $mHtml .= '<div id="modal4" class="modal_div">
                    <button class="modal_close" title="Закрыть окно">X</button>
                    <div class="modalcontent"><h4 style="text-align: center; margin: 0;margin-bottom: 5px;" id="modtitle">Введите данные приказа</h4>
                        <p><label for="edate">Дата приказа </label><input type="text" name="edate" id="edate" value="" size="10">
                        <label for="epnumber" style="float: right;">Номер приказа </label><input type="text" name="epnumber" id="epnumber" size="10" style="float: right;"></p>
                        <p align="center">Причина отчисления<br><input type="text" name="reason" id="reason" value="невыполнение учебно-тематического плана"></p>
                        <div class="warn">Приказ уже был сформирован!</div>
                        <div class="warn" id="experror"></div>
                        <p align="center" id="ernblock"><input type="checkbox" name="esave" id="esave" checked="checked"><label for="esave"> Сохранить данные приказа</label></p>
                        <button id="pooprint" class="modal_ok">Скачать приказ</button>
                        </div>
                </div>';
        $mHtml .= '<div id="modal5" class="modal_div">
                    <button class="modal_close" title="Закрыть окно">X</button>
                    <div class="modalcontent"><h4 style="text-align: center; margin: 0;margin-bottom: 5px;" id="modtitle">Введите данные приказа</h4>
                        <p><label for="enddate">Дата приказа </label><input type="text" name="enddate" id="enddate" value="" size="10">
                        <label for="endpnumber" style="float: right;">&nbsp;Номер приказа</label><input type="text" name="endpnumber" id="endpnumber" size="10" style="float: right;"></p>
                        <p align="center"><label for="veddate">Дата ведомости/протокола </label><br><input type="text" name="veddate" id="veddate" value="" size="10"></p>
                        <div class="warn">Приказ уже был сформирован!</div>
                        <div class="warn" id="endxperror"></div>
                        <p align="center" id="endrnblock"><input type="checkbox" name="endsave" id="endsave" checked="checked"><label for="endsave"> Сохранить данные приказа</label></p>
                        <button id="poeprint" class="modal_ok">Скачать приказ</button>
                        </div>
                </div>';
        $this->addModalContent($mHtml);
    }

    /** ==============================================================================================================
     * Создает новую группу слушателей для курса $course
     * @param Integer $course - id  курса
     * @param String $name - название группы
     * @return boolean
     */
    public function crossGroup(int $oldgroup, int $newgroup, array $student)
    {
        if (!$this->isAuthorized()) {
            return false;
        }
        if (!$this->isRule(RULE_MODERATE)) {
            $this->setError('Вы не имеете доступа к этому функционалу! Обратитесь к администратору!');
            return false;
        }
        $insertId = \QB::table('applications')->where('group', $oldgroup)->whereIn('applications.id', $student)->update(array('group' => $newgroup));
        if (is_null($insertId)) {
            $this->setError('Ошибка перевода слушателей в другую группу!');
            return false;
        }
        $this->addRedirect($_SERVER['HTTP_REFERER'], 0);
        return true;
    }

    /** ==============================================================================================================
     * Создает новую группу слушателей для курса $course
     * @param Integer $course - id  курса
     * @param String $name - название группы
     * @return boolean
     */
    public function createGroup(int $course, string $name)
    {
        if (!$this->isAuthorized()) {
            return false;
        }
        if (!$this->isRule(RULE_MODERATE)) {
            $this->setError('Вы не имеете доступа к этому функционалу! Обратитесь к администратору!');
            return false;
        }
        $insertId = \QB::table('coursegroup')->insert(array('groupname' => $name, 'course' => $course));
        if (is_null($insertId)) {
            $this->setError('Ошибка создания новой группы!');
            return false;
        }
        $this->addRedirect($_SERVER['HTTP_REFERER'], 0);
        return true;
    }

    /** ==============================================================================================================
     * Создает новую группу слушателей для курса $course
     * @param Integer $id группа
     * @param String $name - название группы
     * @return boolean
     */
    public function renameGroup(int $id, string $name)
    {
        if (!$this->isAuthorized()) {
            return false;
        }
        if (!$this->isRule(RULE_MODERATE)) {
            $this->setError('Вы не имеете доступа к этому функционалу! Обратитесь к администратору!');
            return false;
        }
        $insertId = \QB::table('coursegroup')->where('id', $id)->update(array('groupname' => $name));
        if (is_null($insertId)) {
            $this->setError('Ошибка переименования группы!');
            return false;
        }
        return true;
    }

    /** ========================================================================
     *  удаляет группу
     * @param int $id - id группы
     * @return boolean
     */
    public function deleteGroup(int $id)
    {
        if (!$this->isAuthorized()) {
            $this->setError('Вы не авторизованы! Сначала <a href="/?act=loginform">войдите под своей учетной записью</a>!');
            return false;
        }
        if (!$this->isRule(RULE_MODERATE)) {
            $this->setError('Вы не имеете доступа к этому функционалу! Обратитесь к администратору!');
            return false;
        }
        $insertId = \QB::table('coursegroup')->where('coursegroup.id', $id)->delete();
        if (is_null($insertId)) {
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
    public function getOrderAddInfo(string $group)
    {
        $res = array();
        $query = \QB::table('coursegroup')->select('id', 'orderAddNumber', \QB::raw("DATE_FORMAT(orderAddDate,'%d.%m.%Y') as orderAddDate"))->find($group);
        Error::pdump($query);
        if (is_null($query->orderAddNumber)) {
            $res['pNumber'] = Settings::getSetting('orderAddNumber');
            $res['date'] = date('d.m.Y');
            $res['saved'] = false;
            $res['error'] = '';
        } else {
            $res['pNumber'] = $query->orderAddNumber;
            $res['date'] = $query->orderAddDate;
            $res['saved'] = true;
            $res['error'] = '';
        }
        $this->addContent(json_encode($res));
    }

    /**=========================================================================
     * Возвращает json c данными приказа об окончании курса. Если приказ не печатался, возвращается значение из таблицы настроек, иначе читается номер из таблицы группы
     * @param Integer $group - id группы
     */
    public function getOrderEndInfo(string $group)
    {
        $res = array();
        $query = \QB::table('coursegroup')->select('id', 'orderEndNumber', \QB::raw("DATE_FORMAT(orderEndDate,'%d.%m.%Y') as orderEndDate"), \QB::raw("DATE_FORMAT(vedDate,'%d.%m.%Y') as vedDate"))->find($group);
        Error::pdump($query);
        if (is_null($query->orderEndNumber)) {
            $res['pNumber'] = Settings::getSetting('orderEndNumber');
            $res['date'] = date('d.m.Y');
            $res['veddate'] = date('d.m.Y');
            $res['saved'] = false;
            $res['error'] = '';
        } else {
            $res['pNumber'] = $query->orderEndNumber;
            $res['date'] = $query->orderEndDate;
            $res['veddate'] = $query->vedDate ?? date('d.m.Y');
            $res['saved'] = true;
            $res['error'] = '';
        }
        $this->addContent(json_encode($res));
    }

    /**=========================================================================
     * Возвращает json c данными приказа об отчислении. Если приказ не печатался, возвращается значение из таблицы настроек, иначе читается номер из таблицы заявок
     * @param Integer $group - id группы
     */
    public function getOrderExpInfo(string $group)
    {
        $res = array();
        $query = \QB::table('applications')->select('id')->where('group', $group)->where('status', APP_STATUS_EXPULSION);
        $count = $query->count();

        if ($count == 0) {
            $res['error'] = 'В группе нет отчисленных!';
        } else {
            $res['error'] = '';
        }

        $query = \QB::table('coursegroup')->select('id', 'orderExpNumber', 'reason', \QB::raw("DATE_FORMAT(orderExpDate,'%d.%m.%Y') as orderExpDate"))->find($group);
        Error::pdump($query);
        if (is_null($query->orderExpNumber)) {
            $res['pNumber'] = Settings::getSetting('orderExpNumber');
            $res['reason'] = 'невыполнение учебно-тематического плана';
            $res['date'] = date('d.m.Y');
            $res['saved'] = false;
        } else {
            $res['pNumber'] = $query->orderExpNumber;
            $res['date'] = $query->orderExpDate;
            $res['reason'] = $query->reason;
            $res['saved'] = true;
        }
        $this->addContent(json_encode($res));
    }

    /** ========================================================================
     * Помечает слушателей на отчисление
     * @param array $student - id слушателей
     */
    public function expulseStudent(array $student)
    {
        if (!$this->isRule(RULE_MODERATE)) {
            $this->setError('Вы не имеете доступа к этому функционалу! Обратитесь к администратору!');
            return false;
        }
        $query = \QB::table('applications')->whereIn('id', $student)->update(array('status' => APP_STATUS_EXPULSION, 'status_changed' => 1));
        //Error::pdump($query->getQuery()->getSql(),'sql');
    }

    /** ========================================================================
     * Раздел "Документооборот"
     */
    public function docMaster(int $pageN = 1)
    {
        //$pages = ($pageN ?? 1);
        $this->addContent('<h4 style="text-align: center">Документы</h4>');
        $sql = "SELECT SQL_CALC_FOUND_ROWS 
                        g.`id`, 
                        g.`groupname`, 
                        g.`course`, 
                        g.`orderAddNumber`, 
                        g.`orderEndNumber`, 
                        DATE_FORMAT(g.orderAddDate,'%d.%m.%Y') as orderAddDate, 
                        DATE_FORMAT(g.orderEndDate,'%d.%m.%Y') as orderEndDate, 
                        c.`name`, 
                        DATE_FORMAT(c.start,'%d.%m.%Y') as start, 
                        DATE_FORMAT(c.stop,'%d.%m.%Y') as stop, 
                        c.`hours`, 
                        c.`curator`, 
                        p.`predmet`, 
                        cc.name AS catname, 
                        (SELECT COUNT(*) FROM `uts_applications` a WHERE a.`status` = 2 AND a.group = g.id) as count,
                        (SELECT COUNT(*) FROM `uts_applications` a WHERE a.`status` = 6 AND a.group = g.id) as excount
                FROM `uts_coursegroup` `g` 
                    INNER JOIN `uts_course` c ON c.`id` = g.`course` 
                    INNER JOIN `uts_ccategory` cc ON cc.`id` = c.`parent` 
                    INNER JOIN `uts_predmet` p ON p.`id` = c.`predmet` 
                ORDER BY c.`start` DESC, c.`stop` DESC
                LIMIT " . ($pageN - 1) * Settings::getSetting('limitOrderOnPage') . ", " . Settings::getSetting('limitOrderOnPage');
        Error::pdump($sql, 'SQL');
        $query = \QB::query($sql);
        $result = $query->get();
        //Error::pdump($result, 'Группы из базы');
        //if (!is_null($pageN)){
        $query = \QB::query("SELECT FOUND_ROWS() as `count`");
        $pages = $query->get()[0]->count;
        Error::pdump($pages, 'всего групп');
        //}
        $pCount = (int)ceil($pages / Settings::getSetting('limitOrderOnPage'));
        Error::pdump($pCount, 'pCpount');
        $pager = '<span class="pttl">Страницы</span> <div class="pager">' . ($pageN == 1 ? '' : '<a href="/?act=docs&page=' . ($pageN - 1) . '"><span class="str" title="Предыдущая страница">&laquo;</span></a> ');
        for ($i = 1; $i <= $pCount; $i++) {
            $pager .= '<a href="/?act=docs&page=' . $i . '"><span class="str' . ($i == $pageN ? ' actpg' : '') . '">' . $i . '</span></a> ';
        }
        $pager .= ($pageN == $pCount ? '' : '<a href="/?act=docs&page=' . ($pageN + 1) . '"><span class="str" title="Следующая страница">&raquo;</span></a>') . '</div>';
        //$pager .= getPaginator($pages, Settings::getSetting('limitOrderOnPage'), $pageN, '/?act=docs&page=(:num)');
        $html = '<ul class="docsgrouplist">';
        Error::pdump($result, 'группы');
        //Error::pdump($query->getQuery()->getSql(), 'SQL');
        foreach ($result as $k => $v) {
            if ($v->count != 0) {
                $html .= '<li ' . (!is_null($v->orderAddNumber) ? (!is_null($v->orderEndNumber) ? 'class="green"' : 'class="blue"') : '') . '>'
                    . '<a href="/?act=group&course=' . $v->course . '&group=' . $v->id . '" title="Список группы<br>Отчислить слушателя" class="show-tooltip"><i class="fa-light fa-file-lines"></i></a> [' . $v->count . '] &ndash; '
                    . '<a href="#modal1" class="open_modal show-tooltip" title="Сформировать приказ о зачислении на курс" data-type="2" data-group="' . $v->id . '"><i class="fa-light fa-file-import"></i></a> '
                    . ($v->excount != 0 ? '<a href="#modal2" class="open_modal show-tooltip" title="Сформировать приказ об отчислении с курса" data-type="3" data-group="' . $v->id . '"><i class="fa-light fa-file-excel"></i></a> ' : '')
                    . '<a href="#modal3" class="open_modal show-tooltip" title="Сформировать приказ об окончании курса" data-type="4" data-group="' . $v->id . '"><i class="fa-light fa-file-export"></i></a> '
                    . $v->groupname . ' <small>[ ' . $v->curator . ' ]</small> &laquo;<a href="/?act=showcourse&hidectrl=1&id=' . $v->course . '">' . $v->name . '</a>&raquo; &ndash; ' . $v->hours . ' ч.  [' . $v->predmet . '] c ' . $v->start . ' по ' . $v->stop . '</li>';
            }
        }
        $html .= '</ul>';
        $this->addContent($pager);
        $this->addContent($html);
        $this->addContent($pager);
        $html = '<h4 style="text-align: center; margin: 0;margin-bottom: 5px;" id="modtitle">Введите данные приказа</h4>
                        <input type="hidden" name="group" id="group" value=""><input type="hidden" name="type" id="type" value="">
                        <p><label for="date">Дата приказа </label><input type="text" name="date" id="date" value="" size="10">
                        <label for="pnumber" style="float: right;">Номер приказа </label><input type="text" name="pnumber" id="pnumber" size="10" style="float: right;"></p>
                        <div class="warn">Приказ уже был сформирован!</div>
                        <p align="center" id="rnblock"><input type="checkbox" name="save" id="save" checked="checked"><label for="save"> Сохранить данные приказа</label></p>
                        <button id="pozprint" class="modal_ok">Скачать приказ</button></div></div>';
        $html .= '<div id="modal2" class="modal_div">
                    <button class="modal_close" title="Закрыть окно">X</button>
               <div class="modalcontent">
               <h4 style="text-align: center; margin: 0;margin-bottom: 5px;" id="modtitle">Введите данные приказа</h4>
                        <input type="hidden" name="egroup" id="egroup" value=""><input type="hidden" name="etype" id="etype" value="">
                        <p><label for="edate">Дата приказа </label><input type="text" name="edate" id="edate" value="" size="10">
                        <label for="epnumber" style="float: right;">Номер приказа </label><input type="text" name="epnumber" id="epnumber" size="10" style="float: right;"></p>
                        <p align="center">Причина отчисления<br><input type="text" name="reason" id="reason" value="невыполнение учебно-тематического плана"></p>
                        <div class="warn">Приказ уже был сформирован!</div>
                        <p align="center" id="ernblock"><input type="checkbox" name="esave" id="esave" checked="checked"><label for="esave"> Сохранить данные приказа</label></p>
                        <button id="pooprint" class="modal_ok">Скачать приказ</button>';
        $html .= '</div></div>
                    <div id="modal3" class="modal_div">
                    <button class="modal_close" title="Закрыть окно">X</button>
               <div class="modalcontent">
               <h4 style="text-align: center; margin: 0;margin-bottom: 5px;" id="modtitle">Введите данные приказа</h4>
                        <input type="hidden" name="endgroup" id="endgroup" value=""><input type="hidden" name="endtype" id="endtype" value="">
                         <p><label for="enddate">Дата приказа </label><input type="text" name="enddate" id="enddate" value="" size="10">
                        <label for="endpnumber" style="float: right;">&nbsp;Номер приказа</label><input type="text" name="endpnumber" id="endpnumber" size="10" style="float: right;"></p>
                        <p align="center"><label for="veddate">Дата ведомости/протокола </label><br><input type="text" name="veddate" id="veddate" value="" size="10"></p>
                        <div class="warn">Приказ уже был сформирован!</div>
                        <div class="warn" id="endxperror"></div>
                        <p align="center" id="endrnblock"><input type="checkbox" name="endsave" id="endsave" checked="checked"><label for="endsave"> Сохранить данные приказа</label></p>
                        <button id="poeprint" class="modal_ok">Скачать приказ</button>';
        $this->addModalContent($html);
        $this->addJQCode(file_get_contents(dirname(__FILE__) . '/docMaster.js'));
    }

    public function printPOZ($data)
    {
        $query = \QB::table('applications')->select('id')->where('group', $data['group']);
        $result = $query->get();
        $arr = array();
        foreach ($result as $v) {
            $arr[] = $v->id;
        }
        $data['student'] = json_encode($arr);
        $data['doctype'] = 2;
        $dc = new \OnlineRecord\DocCreator($data);
        //Error::pdump(json_encode($arr),'json');
        //$this->addJQCode('location.href = "/?act=printdoc&doctype=2&student='.addcslashes(json_encode($arr),'"').'";');
        //$this->addRedirect('/?act=printdoc&doctype=2&student="'.json_encode($arr).'"', 0);
        //$this->addContent(json_encode($arr));
    }

    public function printPOO($data)
    {
        $query = \QB::table('applications')->select('id')->where('group', $data['group'])->where('status', APP_STATUS_EXPULSION);
        $result = $query->get();
        $arr = array();
        foreach ($result as $v) {
            $arr[] = $v->id;
        }
        $data['student'] = json_encode($arr);
        $data['doctype'] = 3;
        $dc = new \OnlineRecord\DocCreator($data);
    }

    public function printPOE($data)
    {
        $query = \QB::table('applications')->select('id')->where('group', $data['group'])->where('status', APP_STATUS_ACCEPTED);
        $result = $query->get();
        $arr = array();
        foreach ($result as $v) {
            $arr[] = $v->id;
        }
        $data['student'] = json_encode($arr);
        $data['doctype'] = 4;
        $dc = new \OnlineRecord\DocCreator($data);
    }


    /** ========================================================================================================================================
     * Заменяет в LK->Ghtml все шаблоны на значения
     * @param array $arr
     */
    public function parseHtml($arr)
    {
        foreach ($arr as $k => $v) {
            if (!is_null($v))
                $this->Ghtml = str_replace($k, $v, $this->Ghtml);
            else
                $this->Ghtml = str_replace($k, '', $this->Ghtml);
        }
    }

    //========================================================================================================================================
    public function getHtml()
    {
        if (is_null($this->Ghtml)) {

            //Error::pdump((isset($_COOKIE['DEBUG']) ? $this->decrypt($_COOKIE['DEBUG']) : 'Нихьт'), 'Coock');

            // Если пришел запрос не AJAX
            if (!AJAX) {
                $this->Ghtml = file_get_contents(dirname(__FILE__) . '/tmpl.html');

                //Error::pdump($this->Ghtml);
                if ($this->isAuthorized())
                    $login = '<a href="/?act=logout" class="show-tooltip" title="Выйти из личного кабинета"><i class="fa-light fa-right-from-bracket"></i> Выйти</a>';
                else
                    $login = '<a href="/?act=loginform" class="show-tooltip" title="Войти в личный кабинет"><i class="fa-light fa-right-to-bracket"></i> Войти</a>';
                $this->addRtemplate('{%LOGIN%}', $login);

                $this->addRtemplate('{%ADMIN%}', $this->isRule(RULE_ADMIN) ? '<li><a href="?act=admin" class="show-tooltip" title="Админ-панель"><i class="fa-light fa-gear"></i> Админка</a></li>' : '');
                $this->addRtemplate('{%USERS%}', $this->isRule(RULE_MODERATE) ? '<li><a href="?act=userlist" class="show-tooltip" title="Пользователи"><i class="fa-light fa-user"></i> Слушатели</a></li>' : '');
                $this->addRtemplate('{%DOCS%}', $this->isRule(RULE_DOCS) ? '<li><a href="?act=docs" class="show-tooltip" title="Документооборот"><i class="fa-light fa-file-certificate"></i> Документооборот</a></li>' : '');
                //$this->addRtemplate('{%STATISTICS%}', $this->isRule(RULE_CATALOGUE) || $this->isRule(RULE_REPORTS) ? '<li><a href="?act=statistics" class="show-tooltip" title="Статистика">Статистика</a></li>' : '');
                //$this->addRtemplate('{%MODERATE%}', $this->isRule(RULE_MODERATE) ? '<li><a href="?act=cattree" class="show-tooltip" title="Правка категорий/курсов">Управление курсами</a></li>' : '');
                $this->addRtemplate('{%REPORTS%}', $this->isRule(RULE_REPORTS) ? '<li><a href="?act=reports" class="show-tooltip" title="Отчеты и статистика"><i class="fa-light fa-file-chart-column"></i> Отчеты</a></li>' : '');
                $this->addRtemplate('{%PFRO%}', $this->isRule(RULE_REPORTS) ? '<li><a href="?act=pfro" class="show-tooltip" title="Базы ПФРО"><i class="fa-light fa-check-double"></i> ПФРО</a></li>' : '');
                $this->addRtemplate('{%PROFILE%}', $this->isRule(RULE_VIEW) ? '<li><a href="/?act=profile" class="show-tooltip" title="Открыть профиль пользователя"><i class="fa-light fa-id-badge"></i> Мой профиль </a>'
                    . (($this->new_message_count > 0 || $this->appChangedCount > 0 || $this->appModerCount > 0) ? '<span id="messcount">' . ($this->new_message_count + $this->appChangedCount + $this->appModerCount) . '</span>' : '') . '</li>' : '');

                // Если пришел AJAX запрос
            } else {
                if ($this->codeAdded) {
                    $this->Ghtml = '<script>' . "\n"
                        . '{%JSCODE%}' . "\n"
                        . '$(document).ready(function(){' . "\n"
                        . '    {%JQCODE%} ' . "\n"
                        . '});' . "\n"
                        . '</script>' . "\n";
                }
                $this->Ghtml .= '{%CONTENT%}';
            }
        }

        $this->parseHtml($this->templates);
        return $this->Ghtml;
    }

    //========================================================================================================================================
    public function adminPanel()
    {
        if (!$this->isAuthorized()) {
            $this->setError('Вы не авторизованы! Сначала <a href="/?act=loginform">войдите под своей учетной записью</a>!');
            return false;
        }
        if (!$this->isAuthorized()) {
            return false;
        }
        if (!$this->isRule(RULE_ADMIN)) {
            $this->setError('Вы не имеете доступа к этому функционалу! Обратитесь к администратору!');
            return false;
        }
        $divs = array(1 => '', 2 => '', 3 => '');

        switch ($_REQUEST['cat'] ?? 'sys') {
            case 'moder':
                $divs[2] .= 'Руководители курсов';
                $this->addContent($divs[2]);
                break;
            case 'users':
                $this->addContent($this->getSearchUserForm());
                $this->addJQCode(file_get_contents(dirname(__FILE__) . '/searchUser.js'));
                $this->addRtemplate('{%USERLIST%}', $this->getUserList(array('type' => $_REQUEST['type'] ?? 'last',
                    'mode' => $_REQUEST['mode'] ?? null,
                    'text' => $_REQUEST['searchtext'] ?? null)));
                break;

            case 'sys':
            default:
                $this->addContent($this->getSystemSettings());
                $this->addJQCode(file_get_contents(dirname(__FILE__) . '/adminpanel.js'));
                $this->addJQCode(file_get_contents(dirname(__FILE__) . '/searchUser.js'));
                $this->addHeader('<script src="/inc/tinysort.min.js"></script>');
            //$this->searchUser('кун, малова, 000-000');
        }

    }

    //===========================================================================================================================

    /** записывает в {%CONTENT%} html с настройками типа, указанного в $type
     * @param int $type
     * @return void
     */
    public function showSettings(int $type)
    {
        $query = \QB::table('settings')->where('type', $type)->orderBy('name');
        $result = $query->get();
        Error::pdump($result, 'настройки');
        $html = '<form class="adminsettings" action="/?act=updateset" enctype="multipart/form-data" method="post">'
            . '<table id="setTable"><tr><th>Имя параметра</th><th>Значение</th><th>Права доступа</th><th>Описание</th></tr>';
        foreach ($result as $v) {
            $html .= '<tr><td>' . $v->name . '</td><td><input type="text" name="set[' . $v->id . ']" value="' . $v->value . '"></td><td><input type="text" name="rules[' . $v->id . ']" value="' . $v->rules . '"></td><td>' . $v->desc . '</td></td>';
        }
        $html .= '</table><input type="submit" name="saveSettings" value="Сохранить настройки"></form>';
        $this->addContent($html);
        //return $html;
    }
    //====================================================================================================

    /** сохраняет системные настройки
     * @param array $values
     * @param array $rules
     * @return void
     */
    public function saveSettings(array $values, array $rules)
    {
        $query = 'INSERT INTO ' . \OnlineRecord\config['prefix'] . 'settings (`id`, `value`, `rules`) VALUES ';
        $first = true;
        foreach ($values as $k => $v) {
            Error::pdump($k, 'k');
            $query .= ($first ? '' : ',') . '(' . $k . ', ' . \QB::pdo()->quote($v) . ', ' . $rules[$k] . ')';
            $first = false;
        }
        $query .= ' ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `rules` = VALUES(`rules`)';
        Error::pdump($query, 'SQL');
        $query = \QB::query($query);
        Error::pdump($query, 'update');
        $this->addRedirect('/?act=admin', 0);
    }

    //========================================================================================================================================
    public function catalogue()
    {
        if (!$this->isAuthorized()) {
            $this->setError('Вы не авторизованы! Сначала <a href="/?act=loginform">войдите под своей учетной записью</a>!');
            return false;
        }

        //Error::pdump('$cat='.$cat);
        $this->addContent('<h4>Справочники</h4>');
    }

    //========================================================================================================================================
    public function getReports($id)
    {
        if (!$this->isAuthorized()) {
            $this->setError('Вы не авторизованы! Сначала <a href="/?act=loginform">войдите под своей учетной записью</a>!');
            return false;
        }
        $R = new Report($id);
        $this->addContent($R->getReport());
        $this->setTitle('Отчеты');
    }

    //========================================================================================================================================
    public function showAllMessages()
    {
        if (!$this->isAuthorized()) {
            $this->setError('Вы не авторизованы! Сначала <a href="/?act=loginform">войдите под своей учетной записью</a>!');
            return false;
        }
        $this->setTitle('Сообщения');
        $html = '';
        /*$query = \QB::table('messages')->select('*')->where('messages.owner', $this->user->id)->orderBy('sended');
        $result = $query->get();
        Error::pdump($result, 'Messages');*/
        foreach ($this->messages as $v) {
            $html .= '<div class="formrow shadow round5border dgraybg margin5updown message-block ">'
                . '<p class="message-title ' . ($v->viewed == 0 ? 'noviewed' : 'viewed') . '" data-id="' . $v->id . '" data-viewed="' . ($v->viewed == 0 ? 'false' : 'true') . '">' . $v->title . '</p>'
                . '<div class="message-body">' . $v->message
                . '<div class="message-link">' . (is_null($v->link) ? '' : '<a href="' . SITE . $v->link . '">' . SITE . $v->link . '</a>') . '</div>'
                . '</div>'
                . '</div>';
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
            . '});' . "\n";
        $js .= '$(".open_modal").on("click", function(){ '
            . '$(".modal_div").css("width", "800px");'
            . '$(".modal_div").css("marginLeft", "-400px");'
            . '$(".modalcontent").load("/?act=showcourse&id=" + $(this).data("id"), { hidectrl : 1}); '
            . '}); ';
        $this->addContent($html);
        $this->addJQCode($js);
    }

    //========================================================================================================================================
    public function setMessageViewed($id, $set = 1)
    {
        if (!$this->isAuthorized()) {
            $this->setError('Вы не авторизованы! Сначала <a href="/?act=loginform">войдите под своей учетной записью</a>!');
            return false;
        }
        $data = array(
            'viewed' => $set
        );


        $insertId = \QB::table('messages')->where('id', $id)->update($data);
    }

    //========================================================================================================================================
    public function checkData($id, $type)
    {
        if (!$this->isAuthorized()) {
            $this->setError('Вы не авторизованы! Сначала <a href="/?act=loginform">войдите под своей учетной записью</a>!');
            return false;
        }
        $data = array(
            'checked' => 1
        );
        $insertId = \QB::table($type)->where('id', $id)->update($data);
        $this->addRedirect($_REQUEST['referer'], 0);
    }

    //========================================================================================================================================
    public function addCourse($id, $edit = false)
    {
        if ($id == 0) return false;
        if (!$this->isAuthorized()) {
            $this->setError('Вы не авторизованы! Сначала <a href="/?act=loginform">войдите под своей учетной записью</a>!');
            return false;
        }
        //Проверяем права
        if (!$this->isRule(RULE_MODERATE)) {
            $this->setError('Вы не имеете доступа к этому функционалу! Обратитесь к администратору!');
            return false;
        }

        if ($edit) {
            //$query = \QB::table('ccategory')->select('*')->leftJoin('course', 'course.parent', '=', 'ccategory.id')->where('id', $id);
            $query = \QB::table('course')->select(
                array('course.id',
                    'course.name',
                    'course.extfile',
                    'course.finance',
                    \QB::raw(\OnlineRecord\config['prefix'] . "finsource.source as financeR"),
                    'course.dpp',
                    \QB::raw(\OnlineRecord\config['prefix'] . "dpp.dpp as dppR"),
                    'course.mode',
                    \QB::raw(\OnlineRecord\config['prefix'] . "cmode.mode as modeR"),
                    'course.dist',
                    'course.predmet',
                    \QB::raw(\OnlineRecord\config['prefix'] . "predmet.predmet as predmetR"),
                    'course.form',
                    \QB::raw(\OnlineRecord\config['prefix'] . "form.form as formR"),
                    'course.parent',
                    \QB::raw(\OnlineRecord\config['prefix'] . "ccategory.name as category"),
                    \QB::raw("DATE_FORMAT(" . \OnlineRecord\config['prefix'] . "course.start,'%d.%m.%Y') as start"),
                    \QB::raw("DATE_FORMAT(" . \OnlineRecord\config['prefix'] . "course.stop,'%d.%m.%Y') as stop"),
                    'course.owner',
                    'course.regclosed',
                    'course.hours',
                    'course.curator',
                    'course.curatoremail',
                    'course.curatorphone',
                    'course.mailtocurator',
                    'course.note',
                    'course.chatlink',
                    'course.archived')
            )
                ->leftJoin('finsource', 'finsource.id', '=', 'course.finance')
                ->leftJoin('dpp', 'dpp.id', '=', 'course.dpp')
                ->leftJoin('cmode', 'cmode.id', '=', 'course.mode')
                ->leftJoin('predmet', 'predmet.id', '=', 'course.predmet')
                ->leftJoin('ccategory', 'ccategory.id', '=', 'course.parent')
                ->leftJoin('form', 'form.id', '=', 'course.form')
                ->where('course.id', $id)
                ->where('course.archived', 0);
        } else {
            $query = \QB::table('ccategory')->select('*')->where('id', $id);
            $this->addRtemplate('{%EDIT%}', '');
        }
        $cat = $query->first();
        Error::pdump($cat, 'Категория курса');

        if ($edit)
            $this->addRtemplate('{%EDIT%}', '<input type="hidden" name="edit" id="edit" value="' . $cat->parent . '">');

        $this->addHeader('<link rel="stylesheet" href="inc/select2.css">');
        $this->addHeader('<script src="inc/select2.full.js"></script>');
        $this->addHeader('<script src="https://cdn.tiny.cloud/1/07shqulid75y2m5g60izwj7u5731ai9mwtjor90ra0smk9ys/tinymce/5/tinymce.min.js" referrerpolicy="origin"></script>');

        $this->addJQCode(file_get_contents(dirname(__FILE__) . '/addCourse.js'));

        $this->addContent(file_get_contents(dirname(__FILE__) . '/addCourse.html'));

        if ($edit)
            $this->addRtemplate('{%ID%}', '<input type="hidden" name="id" id="id" value="' . $cat->id . '">');
        else
            $this->addRtemplate('{%ID%}', '<input type="hidden" name="cat" id="cat" value="' . $cat->id . '">');
        $this->addRtemplate('{%CATEGORY NAME%}', '&laquo;' . ($edit ? $cat->category : $cat->name) . '&raquo;');
        if ($edit)
            $this->addJQCode('$("#newcourse").text("Править");');
        $this->addRtemplate('{%COURSE NAME%}', $edit ? $cat->name : null);
        $this->addRtemplate('{%COURSE NOTE%}', $edit ? $cat->note : null);
        $this->addRtemplate('{%PREDMET LIST%}', $this->getPredmets($edit ? $cat->predmet : null));
        $this->addRtemplate('{%COURSE START%}', $edit ? $cat->start : null);
        $this->addRtemplate('{%COURSE STOP%}', $edit ? $cat->stop : null);
        $this->addRtemplate('{%FORM LIST%}', $this->getForms($edit ? $cat->form : null));
        $this->addRtemplate('{%COURSE DIST%}', $edit ? ($cat->dist == 1 ? 'checked="checked"' : '') : '');
        $this->addRtemplate('{%COURSE HOURS%}', $edit ? $cat->hours : null);
        $this->addRtemplate('{%FINANCE LIST%}', $this->getFinanceSoursce($edit ? $cat->finance : null));
        $this->addRtemplate('{%DPP LIST%}', $this->getDpp($edit ? $cat->dpp : null));
        $this->addRtemplate('{%MODE LIST%}', $this->getModes($edit ? $cat->mode : null));
        $this->addRtemplate('{%COURSE CURATOR%}', $edit ? $cat->curator : null);
        $this->addRtemplate('{%CURATOR EMAIL%}', $edit ? $cat->curatoremail : null);
        $this->addRtemplate('{%CURATOR PHONE%}', $edit ? $cat->curatorphone : null);
        $this->addRtemplate('{%MAILTO CURATOR%}', $edit ? ($cat->mailtocurator == 1 ? 'checked="checked"' : '') : '');
        $this->addRtemplate('{%CHAT LINK%}', $edit ? $cat->chatlink : null);
        $this->addRtemplate('{%REG CLOSED%}', $edit ? ($cat->regclosed == 1 ? 'checked="checked"' : '') : '');
        if (isset($cat->extfile) && !is_null($cat->extfile))
            $this->addJQCode('document.querySelector("#coverimg").style.backgroundImage = "url(/upload/covers/cover_' . $cat->id . '.' . $cat->extfile . ')";');

        $html = '<h4 style="text-align: center; margin: 0;margin-bottom: 5px;" isd="modtitle">Добавить категорию слушателей</h4>'
            . '<form id="addnewpredmet" action="/?act=addnewpredmet" enctype="multipart/form-data" method="post">'
            . '<input type="text" name="predmetname" id="predmetname" style="width: 98%; margin-bottom: 15px;">'
            . '<input type="submit" value="Добавить категорию" class="modal_ok">'
            . '</form>';
        $this->addModalContent($html);
    }//function addCourse

    //========================================================================================================================================
    public function saveCourse($id = null)
    {
        if (!$this->isAuthorized()) {
            $this->setError('Вы не авторизованы! Сначала <a href="/?act=loginform">войдите под своей учетной записью</a>!');
            return false;
        }
        //Проверяем права
        if (!$this->isRule(RULE_MODERATE)) {
            $this->setError('Вы не имеете доступа к этому функционалу! Обратитесь к администратору!');
            return false;
        }
        Error::pdump($id, "ID");
        $edit = !is_null($id);
        Error::pdump($edit, "Edit?");
        $ds1 = \DateTime::createFromFormat('d.m.Y', trim(htmlspecialchars($_REQUEST['start'])));
        $ds2 = \DateTime::createFromFormat('d.m.Y', trim(htmlspecialchars($_REQUEST['stop'])));
        //return false;
        $data = array(
            'owner' => $this->user->id,
            'predmet' => filter_var($_REQUEST['predmet'], FILTER_SANITIZE_NUMBER_INT),
            'form' => filter_var($_REQUEST['form'], FILTER_SANITIZE_NUMBER_INT),
            'hours' => filter_var($_REQUEST['hours'], FILTER_SANITIZE_NUMBER_INT),
            'finance' => filter_var($_REQUEST['finance'], FILTER_SANITIZE_NUMBER_INT),
            'dpp' => filter_var($_REQUEST['dpp'], FILTER_SANITIZE_NUMBER_INT),
            'mode' => filter_var($_REQUEST['mode'], FILTER_SANITIZE_NUMBER_INT),
            'name' => trim(htmlspecialchars($_REQUEST['name'])),
            'note' => trim($_REQUEST['note']),
            'chatlink' => trim(htmlspecialchars($_REQUEST['chatlink'])),
            'start' => $ds1->format('Y-m-d'),
            'stop' => $ds2->format('Y-m-d'),
            'curator' => trim(htmlspecialchars($_REQUEST['curator'])),
            'curatoremail' => trim(htmlspecialchars($_REQUEST['curatoremail'])),
            'curatorphone' => trim(htmlspecialchars($_REQUEST['curatorphone'])),
            'mailtocurator' => isset($_REQUEST['mailtocurator']) ? 1 : 0,
            'regclosed' => isset($_REQUEST['regclosed']) ? 1 : 0,
            'dist' => isset($_REQUEST['dist']) ? 1 : 0
        );

        if (!$edit) {
            $data['parent'] = filter_var($_REQUEST['cat'], FILTER_SANITIZE_NUMBER_INT);
            $insertId = \QB::table('course')->insert($data);
            \QB::table('coursegroup')->insert(array('course' => $insertId));
        } else {
            $insertId = \QB::table('course')->where('course.id', $id)->update($data);
        }


        if ($_FILES['cover']['tmp_name'] != '' && !is_null($insertId)) {
            $extfile = substr(strrchr($_FILES['cover']['name'], '.'), 1);
            $uploadfile = uploaddir . 'covers/cover_' . ($edit ? $id : $insertId) . '.' . $extfile;
            //unlink($uploadfile);
            if ($_FILES['cover']['tmp_name'] != '') {
                if (move_uploaded_file($_FILES['cover']['tmp_name'], $uploadfile)) {
                    $data['extfile'] = $extfile;
                    $insertId = \QB::table('course')->where('id', ($edit ? $id : $insertId))->update($data);
                    Error::pdump('Обложка курса загружена');

                    $image = new \Imagick($uploadfile);
                    $iw = $image->getImageWidth();
                    $ih = $image->getImageHeight();
                    if ($iw != 500 && $ih != 300) {
                        $horizontal = $iw > $ih;
                        $e = $image->scaleImage((!$horizontal ? 500 : round(300 / $ih * $iw)), ($horizontal ? 300 : round(500 / $iw * $ih)), true);
                        if (!$e) $this->setError('Ошибка масштабирования обложки.');
                        $iw = $image->getImageWidth();
                        $ih = $image->getImageHeight();
                        $e = $image->cropImage(500, 300, (int)(($iw - 500) / 2), (int)(($ih - 300) / 2));
                        if (!$e) $this->setError('Ошибка кадрирования обложки.');
                        $e = $image->writeImage($uploadfile);
                        if (!$e) $this->setError('Ошибка записи обработанной обложки.');
                    }
                } else {
                    Error::pdump('Ошибка загрузки Обложки.');
                    $this->setError('Обложку загрузить не удалось.');
                }
            }
        }
        if (is_null($insertId)) {
            $this->addContent('Ошибка записи курса в базу!');
            unlink($uploadfile);
            return false;
        }
        $this->addContent('Курс сохранен, сейчас вы будете перемещены в категорию курса!');

        $this->addRedirect(SITE . '/?act=show&cat=' . (isset($_REQUEST['edit']) ? filter_var($_REQUEST['edit'], FILTER_SANITIZE_NUMBER_INT) : filter_var($_REQUEST['cat'], FILTER_SANITIZE_NUMBER_INT)), 5);
    }//function saveCourse

    //========================================================================================================================================
    public function addNewPredmet($name)
    {
        //Error::pdump('добавляем категорию '.$name);
        if ($name === 0) return false;
        if (!$this->isAuthorized()) {
            $this->setError('Вы не авторизованы! Сначала <a href="/?act=loginform">войдите под своей учетной записью</a>!');
            return false;
        }
        //Проверяем права
        if (!$this->isRule(RULE_MODERATE)) {
            $this->setError('Вы не имеете доступа к этому функционалу! Обратитесь к администратору!');
            return false;
        }
        $data = array(
            'predmet' => $name
        );
        $insertId = \QB::table('predmet')->insert($data);
        if (is_null($insertId)) {
            $this->addContent('Ошибка записи категории в базу!');
            return false;
        }
        $this->addContent($this->getPredmets($insertId));
        $this->addContent('<script>$(\'.js-select2\').select2({	placeholder: "Выберите категорию слушателей", language: "ru"});</script>');
    }//function addNewPredmet

    //========================================================================================================================================
    public function deleteCategory($id)
    {
        if ($id == 0) return false;
        if (!$this->isAuthorized()) {
            $this->setError('Вы не авторизованы! Сначала <a href="/?act=loginform">войдите под своей учетной записью</a>!');
            return false;
        }
        //Проверяем права
        if (!$this->isRule(RULE_CATEGORY)) {
            $this->setError('Вы не имеете доступа к этому функционалу! Обратитесь к администратору!');
            return false;
        }

//FIXME: обработать удаление категорий и курсов внутри удаляемой категории =====================================
        $insertId = \QB::table('ccategory')->where('id', $_REQUEST['id'])->delete();
        //Error::pdump($insertId, 'Результат записи в БД');

        if (is_null($insertId)) {
            $this->setError('Ошибка удаления категории!');
        } else
            $this->addRedirect(SITE . '/?act=cattree', 0);
    }//function deleteCategory

    //========================================================================================================================================
    public function renameCategory($id)
    {
        if ($id == 0) return false;
        if (!$this->isAuthorized()) {
            $this->setError('Вы не авторизованы! Сначала <a href="/?act=loginform">войдите под своей учетной записью</a>!');
            return false;
        }
        //Проверяем права
        if (!$this->isRule(RULE_CATEGORY)) {
            $this->setError('Вы не имеете доступа к этому функционалу! Обратитесь к администратору!');
            return false;
        }

        $data = array(
            'name' => trim($_REQUEST['name'])
        );

        $insertId = \QB::table('ccategory')->where('id', $id)->update($data);
        //Error::pdump($insertId, 'Результат записи в БД');

        if (is_null($insertId)) {
            $this->setError('Ошибка записи новой категории в базу!');
        } else {
            $this->addRedirect(SITE . '/?act=cattree', 0);
        }

    }//function renameCategory

    //========================================================================================================================================
    public function saveCategory($parent)
    {
        if (!$this->isAuthorized()) {
            $this->setError('Вы не авторизованы! Сначала <a href="/?act=loginform">войдите под своей учетной записью</a>!');
            return false;
        }
        //Проверяем права
        if (!$this->isRule(RULE_CATEGORY)) {
            $this->setError('Вы не имеете доступа к этому функционалу! Обратитесь к администратору!');
            return false;
        }

        $data = array(
            'name' => trim($_REQUEST['name']),
            'owner' => $this->user->id
        );

        if ($parent != 0) {
            // Создаем подкатегорию
            $data['parent'] = $parent;
        }// if ($parent == 0)

        $insertId = \QB::table('ccategory')->insert($data);
        //Error::pdump($insertId, 'Результат записи в БД');

        if (is_null($insertId)) {
            $this->setError('Ошибка записи новой категории в базу!');
        } else {
            $this->addRedirect(SITE . '/?act=cattree', 0);
        }

    }//function saveCategory

    //========================================================================================================================================
    public function catTree($cat = null)
    {
        /*if (!$this->isAuthorized()){
            $this->setError('Вы не авторизованы! Сначала <a href="/?act=loginform">войдите под своей учетной записью</a>!');
            return false;
        }*/

        /*//Проверяем права
        if ( !($this->isRule(RULE_MODERATE) || $this->isRule(RULE_CATEGORY) || $this->isRule(RULE_REPORTS)) ){
            $this->setError('Вы не имеете доступа к этому функционалу! Обратитесь к администратору!');
            return false;
        } */
        if (is_null($cat) || $cat == '') {
            //Выводим дерево курсов

            $subQuery = \QB::table('course')->select(\QB::raw('count(*)'))->where(
                \QB::raw(\OnlineRecord\config['prefix'] .
                    'course.parent = ' . \OnlineRecord\config['prefix'] . 'ccategory.id AND '
                    . \OnlineRecord\config['prefix'] . 'course.archived = 0 ' .
                    (!$this->isRule(RULE_MODERATE) ? 'AND ' . \OnlineRecord\config['prefix'] . 'course.stop >= NOW()' : ''))
            );
            $query = \QB::table('ccategory')->select('ccategory.*')->select(\QB::subQuery($subQuery, 'count'))->orderBy('id', 'ASC');
            $result = $query->get();
            $queryObj = $query->getQuery();
            Error::pdump($result, 'Категории из базы');
            Error::pdump($queryObj->getSql(), 'SQL');

            $tree = $this->admTree($result);
            $this->addRtemplate('{%TITLE%}', 'Список категорий курсов');
            $this->addContent('<ul class="treeline"><li><span class="rootcat">Курсы ЦНППМ "Педагог 13.ру"</span>' . $tree . '</li></ul>');

            if ($this->isRule(RULE_ADMIN) || $this->isRule(RULE_CATEGORY)) {
                $html = '<form method="post" action="/?act=createcat" id="newcatform">Для создания <u><strong>новой корневой категории</strong></u> введите наименование категории и 
                    нажмите кнопку "Создать"<br><input name="name" id="namenewcat" value="" class="maxwidth"><input type="submit" value="Создать" /></form>';
                $this->addContent($html);
            }
            //$this->addContent('</div>');

            $html = '<h4 style="text-align: center; margin: 0;margin-bottom: 5px;" id="modtitle"></h4><form id="param" action="/?act=renamecat" enctype="multipart/form-data" method="post">'
                . '<input type="hidden" name="id" id="catid">'
                . '<input type="text" name="name" id="catname" style="width: 98%;">'
                . '<input type="submit" value="Применить" class="modal_ok"><br><br>'
                . '</form>';

            $this->addRtemplate('{%MODAL CONTENT%}', $html);

            $js = '$(".edit_cat").on("click", function(){' . "\n" . '$("#param").attr("action", "/?act="+$(this).data("param"));' . "\n"
                . '$("#catid").val($(this).data("id"));' . "\n" . '$("#catname").val($(this).data("name"));' . "\n"
                . '$("#modtitle").text($(this).attr("title"));' . "\n" . '});' . "\n";
            $js .= '$(".ibaddsubcat").on("click", function(){' . "\n" . '$("#param").attr("action", "/?act="+$(this).data("param"));' . "\n"
                . '$("#catid").val($(this).data("id"));' . "\n" . '$("#modtitle").text($(this).attr("title"));' . "\n" . '});' . "\n";
            $this->addJQCode($js);
        } else {
            // Показываем курсы в выбраной категории
            $category = \QB::table('ccategory')->where('ccategory.id', $cat);
            $result = $category->first();
            //Error::pdump($result);
            if (is_null($result)) {
                $this->addContent('Некорректные параметры!');
                $this->addRedirect(SITE, 5);
            }
            $this->addRtemplate('{%TITLE%}', 'Список курсов в категории &laquo;' . $result->name . '&raquo;');
            $this->addContent('<div class="header"><h4>Список курсов в категории &laquo;' . $result->name . '&raquo;</h4>'
                . ($this->isRule(RULE_MODERATE) ? ' &nbsp;&nbsp;&nbsp;<span class="button coral fright"><a href="/?act=addcourse&cat=' . $result->id . '">Добавить курс</a></span>' : '') . '</div>');
            Error::pdump($result, 'Категория из базы');

            $subQueryAll = \QB::table('applications')->select(\QB::raw('count(*)'))->where(
                \QB::raw(\OnlineRecord\config['prefix'] .
                    'applications.course = ' . \OnlineRecord\config['prefix'] . 'course.id')
            );
            $subQueryNew = \QB::table('applications')->select(\QB::raw('count(*)'))->where(
                \QB::raw(\OnlineRecord\config['prefix'] .
                    'applications.course = ' . \OnlineRecord\config['prefix'] . 'course.id AND ' . \OnlineRecord\config['prefix'] . 'applications.status = 1')
            );
            //if ($this->isRule(RULE_MODERATE)){}
            $query = \QB::table('course')->select(
                array('course.id', 'course.name', 'course.extfile', 'course.finance',
                    \QB::raw(\OnlineRecord\config['prefix'] . "finsource.source as financeR"),
                    'course.dpp', \QB::raw(\OnlineRecord\config['prefix'] . "dpp.dpp as dppR"),
                    'course.mode',
                    \QB::raw(\OnlineRecord\config['prefix'] . "cmode.mode as modeR"),
                    'course.dist', 'course.predmet',
                    \QB::raw(\OnlineRecord\config['prefix'] . "predmet.predmet as predmetR"),
                    'course.form', \QB::raw(\OnlineRecord\config['prefix'] . "form.form as formR"),
                    'course.parent',
                    \QB::raw("DATE_FORMAT(" . \OnlineRecord\config['prefix'] . "course.start,'%d.%m.%Y') as start"),
                    //'course.stop',
                    \QB::raw("DATE_FORMAT(" . \OnlineRecord\config['prefix'] . "course.stop,'%d.%m.%Y') as stop"),
                    'course.owner',
                    'course.regclosed',
                    'course.hours',
                    'course.curator',
                    'course.curatoremail',
                    'course.curatorphone',
                    'course.mailtocurator',
                    'course.note',
                    'course.chatlink',
                    'course.archived')
            )
                ->select(\QB::subQuery($subQueryAll, 'acount'))
                ->select(\QB::subQuery($subQueryNew, 'ncount'))
                ->leftJoin('finsource', 'finsource.id', '=', 'course.finance')
                ->leftJoin('dpp', 'dpp.id', '=', 'course.dpp')
                ->leftJoin('cmode', 'cmode.id', '=', 'course.mode')
                ->leftJoin('predmet', 'predmet.id', '=', 'course.predmet')
                ->leftJoin('form', 'form.id', '=', 'course.form')
                ->where('course.parent', $result->id)
                ->where('course.archived', 0);
            if ($this->isRule(RULE_MODERATE)) {
                $query->select(\QB::raw('CASE WHEN DATE(' . \OnlineRecord\config['prefix'] . "course.stop) >= DATE(NOW()) THEN 1 ELSE 0 END AS active"))->orderBy('active', 'DESC');
            }
            if (!$this->isRule(RULE_MODERATE)) {
                $query->where(\QB::raw('DATE(' . \OnlineRecord\config['prefix'] . "course.stop) > DATE(NOW())"))->orderBy(array('course.start', 'course.stop'));
            }
            $result = $query->get();
            //$queryObj = $query->getQuery();
            //Error::pdump($queryObj->getSql(), 'SQL');
            Error::pdump($result, 'Курсы из базы');
            //Error::pdump(date('d.m.Y'), 'date');
            $html = '';//'<div class="container">';
            //. '<div>';
            $this->addContent('<style type="text/css">.content{width: 98%;}#container{flex-flow: row wrap;}</style>');
            //-------------------------------------------------------------------------------------------------
            $myCourse = false;
            foreach ($result as $val) {
                if ($this->authorized)
                    $myCourse = (($val->owner == $this->user->id || $this->isRule(RULE_ADMIN) || $this->isRule(RULE_DOCS)) ? true : false);
                if (!$this->isRule(RULE_MODERATE)) {
                    $val->active = true;
                }
                //Error::pdump($myCourse, $val->id.'-Мой курс');

                $html .= '<div class="courseitem'
                    . ($val->active ? '' : ' inactive')
                    . '" >'
                    . '<a href="#modal1" class="open_modal ci" data-id="' . $val->id . '" data-cat="' . $cat . '" '
                    . ($val->active ? 'data-act="1"' : '')
                    . '>'
                    . '<span class="title">&laquo;' . stripslashes($val->name) . '&raquo;</span>'

                    . '<div class="courseimg">'
                    . '<img src="' . (is_null($val->extfile) ? '/img/cover.png' : '/upload/covers/cover_' . $val->id . '.' . $val->extfile) . '">'
                    . '</div>'

                    . '<div class="note">Курсы ' . $val->financeR . '.<br />Тип: ' . $val->dppR . ', ' . $val->modeR . '.<br />Категория слушателей: ' . $val->predmetR . '.<br />'
                    . 'Форма занятий &ndash; ' . $val->formR . ($val->dist == 1 ? ', дистанционная' : '') . '.</div>'

                    . '<div class="datecourse">' . $val->start . ' &ndash; ' . $val->stop . '</div>'
                    . '</a>'
                    . ((!$this->isRule(RULE_MODERATE) || !$myCourse) ? '' :
                        ('<div class="moderate">'
                            . '<a href="/?act=editcourse&id=' . $val->id . '"><i class="fa-light fa-pen-to-square"  title="Редактировать курс"></i></a> '
                            . '<a data-id="' . $val->id . '" class="lockcourse"><i class="fa-light fa-lock' . ($val->regclosed == 0 ? '-open' : '') . '" title="'
                            . ($val->regclosed == 0 ? 'За' : 'От') . 'крыть для записи курс"></i></a> '
                            . '<a href="#"  data-link="' . SITE . '/?act=showcourse&id=' . $val->id . '" class="copylink"><i class="fa-light fa-link"  title="Скопировать ссылку на курс"></i></a>'
                            . '<a href="/?act=inarch&course=' . $val->id . '" onclick="if(window.confirm(\'ВНИМАНИЕ!\n\nВернуть курс из архива сможет только администратор!\n\nВы уверены, что хотите отправить курс в архив?\')==true) {return true;} else {return false;}"><i class="fa-light fa-calendar-arrow-down" title="Отправить курс в архив"></i></a> '
//FIXME!=========================
                            . '<a href="#modal1" class="open_modal au" data-course="' . $val->id . '"><i class="fa-light fa-user-plus" title="Зарегистрировать слушателя на курс"></i></a> '
                            . '<a href="/?act=moderate&course=' . $val->id . '"><i class="fa-light fa-user" title="Заявки на курс"></i></a> '
                            . '<a href="/?act=group&course=' . $val->id . '"><i class="fa-light fa-users" title="Управление группами"></i></a> '
                            . (!$this->isRule(RULE_ADMIN) ? '' : '<a href="#modal1" class="open_modal co" data-course="' . $val->id . '"><i class="fa-sharp fa-light fa-users-gear" title="Сменить модератора курса"></i></a> ')
                            . '<a href="/?act=moderate&course=' . $val->id . '"><img src="/img/' . ($val->ncount > 0 ? 'ani' : '') . 'green.png" class="ibgreen newapp" ><span class="appcount" title="'
                            . $val->ncount . ' новых заявок из ' . $val->acount . '">' . ($val->ncount > 0 ? $val->ncount : $val->acount) . '</span></a> '
                            . '</div>'))
                    . '</div>';
            }
            //-------------------------------------------------------------------------------------------------

            $html .= '</div>'
                . '</div>';

            $this->addJQCode('$(".lockcourse").on("click", function(){ $(this).load("/?act=lockcourse", { id: $(this).data("id") }); });');
            //$(this).load('/cr/adm.php?act=editspravcat', { id: $(this).attr('id'), value: s});

            $this->addContent($html);
            $this->addContent(file_get_contents(dirname(__FILE__) . '/showCourse.html'));
            $this->addJQCode(file_get_contents(dirname(__FILE__) . '/showCourse.js'));
        }
    }//function catTree()


    //========================================================================================================================================
    public function showTree($cat = null)
    {
        //Error::pdump('$cat='.$cat);
        if (is_null($cat) || $cat == '') {
            // Показываем дерево категорий
            //Error::pdump($query->getQuery(), 'query');
            $subQuery = \QB::table('course')->select(\QB::raw('count(*)'))->where(
                \QB::raw(\OnlineRecord\config['prefix'] .
                    'course.parent = ' . \OnlineRecord\config['prefix'] . 'ccategory.id AND ' . \OnlineRecord\config['prefix'] . 'course.archived = 0')
            );
            $query = \QB::table('ccategory')->select('ccategory.*')->select(\QB::subQuery($subQuery, 'count'))->orderBy('id', 'ASC');
            $result = $query->get();
//            Error::pdump($query, 'query');
            Error::pdump($result, 'Категории из базы');

            $tree = $this->tree($result);

            $this->addRtemplate('{%TITLE%}', 'Список категорий курсов');
            $this->addContent('<ul class="treeline"><li><span class="rootcat">Курсы ЦНППМ "Педагог 13.ру"</span>' . $tree . '</li></ul>');


        } else {
            // Показываем курсы в выбраной категории
            $category = \QB::table('ccategory')->where('ccategory.id', $cat);
            $result = $category->first();

            $this->addRtemplate('{%TITLE%}', 'Список курсов в категории &laquo;' . $result->name . '&raquo;');
            $this->addContent('<div class="header"><h4>Список курсов в категории &laquo;' . $result->name . '&raquo;</h4></div>');
            Error::pdump($result, 'Категория из базы');

            $query = \QB::table('course')->select(
                array('course.id', 'course.name', 'course.extfile', 'course.finance',
                    \QB::raw(\OnlineRecord\config['prefix'] . "finsource.source as financeR"),
                    'course.dpp', \QB::raw(\OnlineRecord\config['prefix'] . "dpp.dpp as dppR"), 'course.mode',
                    \QB::raw(\OnlineRecord\config['prefix'] . "cmode.mode as modeR"),
                    'course.dist', 'course.predmet', \QB::raw(\OnlineRecord\config['prefix'] . "predmet.predmet as predmetR"),
                    'course.form', \QB::raw(\OnlineRecord\config['prefix'] . "form.form as formR"), 'course.parent',
                    \QB::raw("DATE_FORMAT(" . \OnlineRecord\config['prefix'] . "course.start,'%d.%m.%Y') as start"),
                    \QB::raw("DATE_FORMAT(" . \OnlineRecord\config['prefix'] . "course.stop,'%d.%m.%Y') as stop"),
                    'course.owner', 'course.regclosed', 'course.hours', 'course.curator', 'course.curatoremail',
                    'course.curatorphone', 'course.mailtocurator', 'course.note', 'course.chatlink', 'course.archived')
            )
                ->leftJoin('finsource', 'finsource.id', '=', 'course.finance')
                ->leftJoin('dpp', 'dpp.id', '=', 'course.dpp')
                ->leftJoin('cmode', 'cmode.id', '=', 'course.mode')
                ->leftJoin('predmet', 'predmet.id', '=', 'course.predmet')
                ->leftJoin('form', 'form.id', '=', 'course.form')
                ->where('course.parent', $result->id)
                ->where('course.archived', 0);
            $result = $query->get();
            Error::pdump($result, 'Курсы из базы');
            $html = '';//'<div class="container">';
            //. '<div>';
            $this->addContent('<style type="text/css">.content{width: 98%;}#container{flex-flow: row wrap;}</style>');
            foreach ($result as $val) {
                $html .= '<div class="courseitem" >'
                    . '<a href="#modal1" class="open_modal" data-id="' . $val->id . '" data-cat="' . $cat . '">'
                    . '<span class="title">&laquo;' . stripslashes($val->name) . '&raquo;</span>'

                    . '<div class="courseimg">'
                    . '<img src="' . (is_null($val->extfile) ? '/img/cover.png' : '/upload/covers/cover_' . $val->id . '.' . $val->extfile) . '">'
                    . '</div>'

                    . '<div class="note">Курсы ' . $val->financeR . '.<br />Тип: ' . $val->dppR . ', ' . $val->modeR . '.<br />Категория слушателей: ' . $val->predmetR . '.<br />'
                    . 'Форма занятий &ndash; ' . $val->formR . ($val->dist == 1 ? ', дистанционная' : '') . '.</div>'

                    . '<div class="datecourse">' . $val->start . ' &ndash; ' . $val->stop . '</div>'
                    . '</a>'
                    . '</div>';
            }
            /*    $html .= '<p><table width="100%" border="1" cellpadding="5" cellspacing="0">'.chr(13);
            $html .= '<tr><th width="50%">Курс повышения квалификации</th><th width="5%">Часы</th><th width="15%">Дата</th><th>Руководитель</th></tr>'.chr(13);
            foreach ($result as $val){
                $html .= '<tr><td>'.
                        ($val->regclosed == 1 ? '<span style="color: red; font-size: x-small;">Запись на курс отключена</span><br>' : '')
                        .'<a href="/index.php?act=showmore&id='.$val->id.'">&laquo;'.stripslashes($val->name).'&raquo;</a><br>['.
                        stripslashes($val->predmet).'] '.'</td><td>'.$val->hours.'</td><td>'.$val->start.' &ndash; '.$val->stop.'</td><td>'.
                        stripslashes($val->curator).'</td></tr>'.chr(13);
            }
            $html .= '</table></p>';*/


            $html .= '</div>'
                . '</div>';

            $this->addContent($html);
            $this->addContent(file_get_contents(dirname(__FILE__) . '/showCourse.html'));
            $this->addJQCode(file_get_contents(dirname(__FILE__) . '/showCourse.js'));
        }


    }

    //========================================================================================================================================
    public function showCourse($id)
    {
        $query = \QB::table('course')->select(
            array('course.id', 'course.name', 'course.extfile', 'course.finance',
                \QB::raw(\OnlineRecord\config['prefix'] . "finsource.source as financeR"),
                'course.dpp', \QB::raw(\OnlineRecord\config['prefix'] . "dpp.dpp as dppR"), 'course.mode',
                \QB::raw(\OnlineRecord\config['prefix'] . "cmode.mode as modeR"),
                'course.dist', 'course.predmet', \QB::raw(\OnlineRecord\config['prefix'] . "predmet.predmet as predmetR"),
                'course.form', \QB::raw(\OnlineRecord\config['prefix'] . "form.form as formR"), 'course.parent',
                \QB::raw("DATE_FORMAT(" . \OnlineRecord\config['prefix'] . "course.start,'%d.%m.%Y') as start"),
                \QB::raw("DATE_FORMAT(" . \OnlineRecord\config['prefix'] . "course.stop,'%d.%m.%Y') as stop"),
                'course.owner', 'course.regclosed', 'course.hours', 'course.curator', 'course.curatoremail',
                'course.curatorphone', 'course.mailtocurator', 'course.note', 'course.chatlink', 'course.archived')
        )
            ->leftJoin('finsource', 'finsource.id', '=', 'course.finance')
            ->leftJoin('dpp', 'dpp.id', '=', 'course.dpp')
            ->leftJoin('cmode', 'cmode.id', '=', 'course.mode')
            ->leftJoin('predmet', 'predmet.id', '=', 'course.predmet')
            ->leftJoin('form', 'form.id', '=', 'course.form')
            ->where('course.archived', 0)
            ->where('course.id', $id)
            ->where('course.archived', 0);
        $u = $query->first();
        Error::pdump($u, 'Курс из базы');
        //Error::pdump($query->getQuery()->getRawSql());
        $this->setTitle('Курс &laquo;' . stripslashes($u->name) . '&raquo;');
        $html = '<p class="ctitle">&laquo;' . $u->name . '&raquo;</p>' .
            '<p class="c1str">' . $u->predmetR . ', ' . $u->hours . ' ' . number($u->hours, array('час', 'часа', 'часов'))
            . ' c ' . $u->start . ' по ' . $u->stop . '. Курсы ' . $u->financeR . '.<br />Тип: ' . $u->dppR . ', ' . $u->modeR . '. Форма занятий &ndash; ' . $u->formR . ($u->dist == 1 ? ', дистанционная' : '') . '</p>' .
            '<p class="cboss">Руководитель: ' . $u->curator . ' (<a href="mailto:' . $u->curatoremail . '">' . $u->curatoremail . '</a>)</p>' .
            '<div class="cnote">' . $u->note . '</div>';

        if ($this->isAuthorized()) {

            $query = \QB::table('pass')->select('pass.id', 'pass.series', 'pass.number', 'citizenship.citizenship', \QB::raw("DATE_FORMAT(" . \OnlineRecord\config['prefix'] . "pass.datedoc,'%d.%m.%Y') as datedoc"))
                ->leftJoin('citizenship', 'citizenship.id', '=', 'pass.citizenship')
                ->where('pass.parent', $this->user->id)
                ->orderBy('pass.id', 'DESC');
            $p = $query->first();
            Error::pdump($p, 'Паспорт');

            $query = \QB::table('diplom')->select('*')
                ->where('diplom.parent', $this->user->id)
                ->orderBy('diplom.id', 'DESC');
            $d = $query->get();
            $diplomCount = $query->count();
            Error::pdump($d, 'Диплом');
            Error::pdump($diplomCount, 'Диплом');

            $query = \QB::table('work')->select('*')
                ->where('work.parent', $this->user->id)
                ->orderBy('work.id', 'DESC');
            $w = $query->get();
            $workCount = $query->count();
            Error::pdump($w, 'Работа');
            Error::pdump($workCount, 'Работа');

            $html .= '<div id="dws" class="formrow shadow round5border dgraybg ' . (($diplomCount == 1 && $workCount == 1) ? 'hidden' : '') . '"><p class="c">Использовать для записи следующие данные:</p>';
            $html .= '<input type="hidden" name="passlist" id="passlist" value="' . $p->id . '">';

            $html .= '<span id="bdiplom" class="' . ($diplomCount == 1 ? 'hidden' : '') . '">Диплом <select name="diplomlist" id="diplomlist" ' . (isset($_REQUEST['hidectrl']) ? 'disabled="disabled"' : '') . '>';
            foreach ($d as $v2) {
                $html .= '<option value="' . $v2->id . '" ' . ((isset($_REQUEST['diplom']) && $_REQUEST['diplom'] == $v2->id) ? 'selected="selected"' : '') . '>' . $v2->qualification . ', ' . $v2->datedoc . '</option>';
            }
            $html .= '</select><br></span>';
            $html .= '<span id="bwork" class="' . ($workCount == 1 ? 'hidden' : '') . '">Работа <select name="worklist" id="worklist" ' . (isset($_REQUEST['hidectrl']) ? 'disabled="disabled"' : '') . '>';
            foreach ($w as $v3) {
                $html .= '<option value="' . $v3->id . '" ' . ((isset($_REQUEST['work']) && $_REQUEST['work'] == $v3->id) ? 'selected="selected"' : '') . '>' . $v3->profession . ', &laquo;' . $v3->organisation . '&raquo;</option>';
            }
            $html .= '</select></span>';
            $html .= '</div>';
        }
        $query = \QB::table('applications')->select('*')
            ->where('applications.course', $id)
            ->where('applications.student', $this->user->id)
            ->orderBy('applications.regdate', 'DESC');
        $a = $query->first();
        //$html .= '###'.serialize($a).'###';
        if ($u->regclosed == 0 && !isset($_REQUEST['hidectrl']) && is_null($a)) {
            $html .= '<input type="button" id="recToCourse" data-courseid="' . $u->id . '" data-coursename="' . $u->name . '" value="Подать заявку на курс" />';
        }

        $origin = new \DateTimeImmutable($u->stop);
        $target = new \DateTimeImmutable();
        $interval = $origin->diff($target);
        Error::pdump($interval->format("%R%a"), 'dd');
        if ($interval->format("%R%d") > 0) {
            $html .= '<span class="wrong">Курс уже закончен!</span>';
        }
        if (!is_null($a)) {
            $html .= '<span class="wrong">Вы уже подали заявку на этот курс!</span>';
        }
        if ($u->regclosed == 1 && !isset($_REQUEST['hidectrl']) && is_null($a)) {
            $html .= '<div class="wrong">Запись на курс закрыта!</div>';
        }
        $this->addContent($html);

        $js = '$("#recToCourse").on("click", function(){'
            . (!$this->isAuthorized() ? ' location.href="/?act=loginform&cat=' . $_REQUEST['cat'] . '";' : '$(".cnote").load("/?act=rectocourse", '
                . '{ courseid: $(this).data("courseid"), coursename: $(this).data("coursename"), pass: $("#passlist").val(), diplom: $("#diplomlist").val(), work: $("#worklist").val() });')
            . '})';
        $this->addJQCode($js);
    }


    //========================================================================================================================================
    public function recToCourse($courseId, $courseName, $userId = NULL, $pass = null, $diplom = null, $work = null, $sendMail = true, $email = NULL)
    {
        if (!$this->isAuthorized()) {
            $this->setError('Вы не авторизованы! Сначала <a href="/?act=loginform">войдите под своей учетной записью</a>!');
            return false;
        }

        if (is_null($email)) {
            $email = $this->user->email;
        }

        $updata = array('course' => $courseId);
        $data = array(
            'student' => (is_null($userId) ? $this->user->id : $userId),
            'course' => $courseId,
            'passport' => $pass,
            'diplom' => $diplom,
            'work' => $work
        );

        $insertId = \QB::table('applications')->onDuplicateKeyUpdate($updata)->insert($data);
        Error::pdump($insertId, 'Результат записи в БД');
        /*
        if(is_null($insertId)){
            $this->setError('Ошибка записи пользователя на курс!');
            return false;
        }*/

        if ($sendMail) {
            $body = '<p>Здравствуйте!<br />Вы получили это письмо, т.к. подали заявку на курс <strong>&laquo;' . $courseName . '&raquo;</strong> на сайте Педагог 13.ру.</p>'
                . '<p>Если вы этого не делали, просто проигнорируйте это письмо.</p>'
                . '<p>Как только руководитель курсов проверит вашу заявку вы получите еще одно уведомление на почту и <a href="' . SITE . '/?act=profile">в личном кабинете</a>.</p>';
            $ok = 'Заявка подана.<br />Вам на почту отправлено уведомление.<br />'
                . 'После рассмотрения вашей заявки вы получите еще одно уведомление на почту и в личный кабинет.<br />'
                . 'Спасибо, что выбираете нас!'
                . '<p></p><p>Это письмо сгенерировано автоматически, отвечать на него не нужно - письмо ни до кого не дойдет!</p>';

            $this->sendMail($email,
                'Подана заявка на курс на сайте Педагог 13.ру',
                $body,
                $ok . '<script>$("#recToCourse").hide(); $("#dws").hide();</script>');
        }
    }

    //========================================================================================================================================
    public function delPassport($id)
    {
        if (!$this->isAuthorized()) {
            $this->setError('Вы не авторизованы! Сначала <a href="/?act=loginform">войдите под своей учетной записью</a>!');
            return false;
        }
        \QB::table('pass')->where('id', '=', $id)->where('parent', '=', $this->user->id)->where('checked', '=', 0)->delete();
        header('Location: /index.php?act=profile');
    }

    //========================================================================================================================================
    public function delDiplom($id)
    {
        if (!$this->isAuthorized()) {
            $this->setError('Вы не авторизованы! Сначала <a href="/?act=loginform">войдите под своей учетной записью</a>!');
            return false;
        }
        \QB::table('diplom')->where('id', '=', $id)->where('parent', '=', $this->user->id)->where('checked', '=', 0)->delete();
        header('Location: /index.php?act=profile');
    }

    //========================================================================================================================================
    protected function copyBtn($param)
    {
        if ($this->user->rulesR >= RULE_VIEW) {
            return '<i class="fa-light fa-copy copy-btn show-tooltip"  title="Скопировать значение в буфер обмена" data-copy="' . $param . '"></i>';
        } else {
            return '';
        }

    }

    //========================================================================================================================================
    public function profile()
    {
        if (!$this->isAuthorized()) {
            $this->setError('Вы не авторизованы! Сначала <a href="/?act=loginform">войдите под своей учетной записью</a>!');
            return false;
        }
        $this->setTitle('Профиль пользователя');
        $this->addContent('<h2 align="center">Мой профиль</h2>');
        //Error::pdump($this, 'LK->');

        $html = '<div class="formrow shadow round5border dgraybg w100ps margin5updown">'
            . '<p class="tcenter" id="mess"><a href="/?act=messages">Мои уведомления [' . $this->message_count . '] ' . ($this->new_message_count > 0 ? '<span id="mess_c" title="' . $this->new_message_count . ' '
                . number($this->new_message_count, array('новое уведомление', 'новых уведомления', 'новых уведомлений')) . '" class="show-tooltip">' . $this->new_message_count . '</span>' : '')
            . '</a></p>'
            . '</div>';

        $html .= '<div class="formrow shadow round5border dgraybg w100ps margin5updown">'
            . '<p class="tcenter" id="app"><a href="/?act=applications">Мои заявки на курсы [' . $this->applicationCount . '] ' . ($this->appChangedCount > 0 ? '<span id="mess_c" title="' . $this->appChangedCount . ' '
                . number($this->new_message_count, array('заявка', 'заявки', 'заявок')) . ' с новым статусом" class="show-tooltip">' . $this->appChangedCount . '</span>' : '')
            . '</a></p>'
            . '</div>';

        if ($this->isRule(RULE_MODERATE)) {
            $html .= '<div class="formrow shadow round5border dgraybg w100ps margin5updown green">'
                . '<p class="tcenter" id="moder"><a href="/?act=moderate">Заявки на мои курсы '
                . ($this->appModerCount > 0 ?
                    '<span id="mess_c" title="' . $this->appModerCount . ' '
                    . number($this->appModerCount, array('новая заявка', 'новых заявки', 'новых заявок')) . '" class="show-tooltip">'
                    . $this->appModerCount . '</span>' : '')
                . '</a></p>'
                . '</div>';
        }


        $html .= '<h3 id="userinfo">Данные пользователя</h3>';//. '<span id="userinfoblock">';

        $html .= '<div id="human" class="formrow shadow round5border dgraybg w100ps">'
            . '<h3>Информация о пользователе' . ' <nobr>[ ID: ' . $this->user->id . ' ]</nobr>'
            . '<a href="?act=edituser&id=' . $this->user->id . '" style="color: black; text-decoration: none;" class="editicon">'
            . '<i class="fa-light fa-pen-to-square fa-2x show-tooltip" title="Редактировать"></i></span></a></h3>'

            . $this->copyBtn($this->user->lastname . ' ' . $this->user->firstname . ' ' . $this->user->fathername) . '&nbsp;Ф.И.О.: '
            . '<div style="display: inline-block"><strong>' . $this->user->lastname . '</strong></div> '
            . '<div style="display: inline-block"><strong>' . $this->user->firstname . '</strong></div> '
            . '<div style="display: inline-block"><strong>' . $this->user->fathername . '</strong></div>'
            . ' <i class="fa-light fa-' . ($this->user->sex == 1 ? 'mars' : 'venus') . ' show-tooltip" title="' . $this->user->sexR . '"></i>'
            . '<br />' . $this->copyBtn($this->user->birthday) . '&nbsp;Дата рождения: <strong>' . $this->user->birthday . '</strong>'
            . '<br />' . $this->copyBtn($this->user->snils) . '&nbsp;СНИЛС: <strong>' . $this->user->snils . '</strong>'
            . '<br />' . $this->copyBtn($this->user->regionR) . '&nbsp;Регион проживания: <strong>' . $this->user->regionR . '</strong>'
            . (!is_null($this->user->distinctrm) ? '<br />' . $this->copyBtn($this->user->distinctrmR) . '&nbsp;Район: <strong>'
                . $this->user->distinctrmR . '</strong>' : '') . ($this->user->cityrm == 1 ? ' [<small>Город</small>]' : '')
            . (!is_null($this->user->distinct) ? '<br />' . $this->copyBtn($this->user->distinct) . '&nbsp;Район: <strong>'
                . $this->user->distinct . '</strong>' : '')
            . '<br />' . $this->copyBtn($this->user->city) . '&nbsp;Населенный пункт: <strong>' . $this->user->city . '</strong>'
            . '<br />' . $this->copyBtn($this->user->address) . '&nbsp;Ул., дом, кв.: <strong>' . $this->user->address . '</strong>'
            . '<br />' . $this->copyBtn($this->user->phone) . '&nbsp;Телефон: <strong>' . $this->user->phone . '</strong>'
            . '<br />' . $this->copyBtn($this->user->email) . '&nbsp;E-mail: <strong>' . $this->user->email . '</strong>'
            . '<br />' . $this->copyBtn($this->user->pedstage) . '&nbsp;Педстаж: <strong>' . $this->user->pedstage . '</strong>'
            . '<br><span' . (!is_null($this->user->extfile) ? ' class="scandoc"><a href="/?act=download&file=snils_'
                . $this->user->id . '.' . $this->user->extfile . '"  target="_blank" title="Скан СНИЛС"><img src="/img/snils.png"></a>' :
                '> <span style="color: red">Скан СНИЛС не загружен</span>')
            . ($this->user->checked == 1 ? '<i class="fa-light fa-badge-check fa-2x good" title="Данные подтверждены"></i>' : '') . '</span>';
        $html .= '</div>';

        $html .= '<h3>Информация о месте работы<a href="?act=addwork&id=' . $this->user->id
            . '" style="color: black; text-decoration: none;" class="addicon"><i class="fa-light fa-circle-plus fa-beat" title="Добавить место работы"></i>'
            . '</a></h3>';
        if (count($this->job) != 0) {
            foreach ($this->job as $v) {
                $html .= '<div class="formrow shadow round5border dgraybg w100ps margin5updown' . ($v->checked == 1 ? ' ochecked' : '') . '">'
                    . ($v->checked != 1 ? '<a href="?act=editwork&id=' . $v->workid
                        . '" style="color: black; text-decoration: none;" class="editicon">'
                        . '<i class="fa-light fa-pen-to-square fa-2x show-tooltip" title="Редактировать"></i></a>' : '') .
                    ($v->gosslujba == 1 ? '[<small style="color: green">Является госслужащим</small>]' : '') .
                    $this->copyBtn($v->organisation) . '&nbsp;Организация: <strong>' . $v->organisation . '</strong>' .
                    '<br />' . $this->copyBtn($v->waddress) . '&nbsp;Почтовый адрес: <strong>' . $v->waddress . '</strong>' .
                    '<br />' . $this->copyBtn($v->city) . '&nbsp;Населенный пункт: <strong>' . $v->city . '</strong>' .
                    ($v->region == 13 ? '<br />' . $this->copyBtn($v->distinctR) . '&nbsp;Район РМ: <strong>' . $v->distinctR . '</strong>' : '') .
                    '<br />' . $this->copyBtn($v->regionR) . '&nbsp;Регион: <strong>' . $v->regionR . '</strong>' .
                    '<br />' . $this->copyBtn($v->profession) . '&nbsp;Должность: <strong>' . $v->profession . '</strong>' .
                    '<br />' . $this->copyBtn($v->stage) . '&nbsp;Стаж в должности: <strong>' . $v->stage . '</strong>' .
                    '<br />' . $this->copyBtn($v->phone) . '&nbsp;Рабочий телефон: <strong>' . $v->phone . '</strong>';
                //Error::pdump($v);
                $html .= '</div>';
            }
        }

        $html .= '<h3 id="passheader">Паспортов в системе: ' . count($this->pass) .
            '<a href="?act=addpassport&id=' . $this->user->id . '" style="color: black; text-decoration: none;" class="addicon">'
            . '<i class="fa-light fa-circle-plus fa-beat" title="Добавить паспорт"></i></a></h3>';
        if (count($this->pass) != 0) {
            foreach ($this->pass as $v) {
                $html .= '<div class="formrow shadow round5border dgraybg w100ps margin5updown' . ($v->checked == 1 ? ' ochecked' : '') . '">' .
                    ($v->checked == 0 ? '<a href="?act=editpassport&id=' . $v->id . '" style="color: black; text-decoration: none;" '
                        . 'class="editicon"><i class="fa-light fa-pen-to-square fa-2x show-tooltip" title="Редактировать"></i></a>' : '') .
                    ($v->checked == 0 ? '<a href="?act=delpassport&id=' . $v->id . '" style="color: black; text-decoration: none;" '
                        . 'class="editicon" onclick="if(window.confirm(\'Внимание!\nОтмена данного действия будет невозможна!\n'
                        . 'Вы уверены, что хотите удалить?\')==true) {return true;} else {return false;}"><i class="fa-light fa-trash-can fa-2x show-tooltip" '
                        . 'title="Удалить"></i></a>' : '')
                    . $this->copyBtn($v->citizenship) . '&nbsp;Гражданство: <strong>' . $v->citizenship . '</strong>' .
                    '<br />' . $this->copyBtn($v->series . '-' . $v->number) . '&nbsp;Номер: <strong>' . $v->series . '-' . $v->number . '</strong>' .
                    '<br />' . $this->copyBtn($v->info) . '&nbsp;Выдан:     <strong>' . $v->info . '</strong>' .
                    '<br />' . $this->copyBtn($v->datedoc) . '&nbsp;Дата выдачи: <strong>' . $v->datedoc . '</strong> ' .
                    '<br><span'
                    . (!is_null($v->extfile) ? ' class="scandoc"><a href="/?act=download&file=pass_' . $v->id . '.' . $v->extfile
                        . '"  target="_blank" title="Скан паспорта"><img src="/img/passport.png"></a>' :
                        '> <span style="color: red">Скан паспорта не загружен</span>')
                    . '</span>';
                //Error::pdump($v);
                $html .= '</div>';
            }
        }

        $html .= '<h3>Дипломов в системе: ' . count($this->diplom) .
            '<a href="?act=adddiplom&id=' . $this->user->id . '" style="color: black; text-decoration: none;" class="addicon">'
            . '<i class="fa-light fa-circle-plus fa-beat" title="Добавить диплом"></i></a></h3>';
        if (count($this->diplom) != 0) {
            foreach ($this->diplom as $v) {
                $html .= '<div class="formrow shadow round5border dgraybg w100ps margin5updown' . ($v->checked == 1 ? ' ochecked' : '') . '">' .
                    ($v->checked == 0 ? '<a href="?act=editdiplom&id=' . $v->id . '" style="color: black; text-decoration: none;" '
                        . 'class="editicon"><i class="fa-light fa-pen-to-square fa-2x show-tooltip" title="Редактировать"></i></a>' : '') .
                    ($v->checked == 0 ? '<a href="?act=deldiplom&id=' . $v->id . '" style="color: black; text-decoration: none;" '
                        . 'class="editicon"  onclick="if(window.confirm(\'Внимание!\nОтмена данного действия будет невозможна!\n'
                        . 'Вы уверены, что хотите удалить?\')==true) {return true;} else {return false;}">'
                        . '<i class="fa-light fa-trash-can fa-2x show-tooltip" title="Удалить"></i></a>' : '')
                    . $this->copyBtn($v->level) . '&nbsp;Уровень образования: <strong>' . $v->level . '</strong>' .
                    '<br />' . $this->copyBtn($v->almamatter) . '&nbsp;ВУЗ, ССУЗ: <strong>' . $v->almamatter . '</strong>' .
                    '<br />' . $this->copyBtn($v->series . '-' . $v->number) . '&nbsp;Номер диплома: <strong>' . $v->series . '-' . $v->number . '</strong>' .
                    '<br />' . $this->copyBtn($v->regnumber) . '&nbsp;Рег. номер: <strong>' . $v->regnumber . '</strong>' .
                    '<br />' . $this->copyBtn($v->datedoc) . '&nbsp;Выдан: <strong>' . $v->datedoc . '</strong>' .
                    '<br />' . $this->copyBtn($v->qualification) . '&nbsp;Квалификация: <strong>' . $v->qualification . '</strong>' .
                    '<br />' . $this->copyBtn($v->stepen) . '&nbsp;Степень: <strong>' . $v->stepen . '</strong>' .
                    '<br />' . $this->copyBtn($v->zvanie) . '&nbsp;Звание: <strong>' . $v->zvanie . '</strong>' .
                    ((!is_null($v->f) || !is_null($v->i) || !is_null($v->o)) ? '<br />' . $this->copyBtn($v->f . ' ' . $v->i . ' ' . $v->o)
                        . '&nbsp;Ф.И.О. в дипломе: <strong>' . $v->f . ' ' . $v->i . ' ' . $v->o . '</strong>' : '') .
                    '<br><span'
                    . (!is_null($v->dextfile) ? ' class="scandoc"><a href="/?act=download&file=diplom_' . $v->id . '.' . $v->dextfile
                        . '"  target="_blank" title="Скан диплома"><img src="/img/diplom.png"></a>' : '> '
                        . '<span style="color: red">Скан диплома не загружен</span>')
                    . '</span>' .
                    ((!is_null($v->f) || !is_null($v->i) || !is_null($v->o))
                        ? (!is_null($v->fextfile)
                            ? '<span class="scandoc"><a href="/?act=download&file=fio_' . $v->id . '.' . $v->fextfile . '"  '
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
    public function userProfile(int $ID)
    {
        if (!$this->isRule(RULE_MODERATE)) {
            $this->setError('Вы не имеете доступа к этой информации!</a>!');
            return false;
        }
        $this->setTitle('Профиль пользователя');
        $this->addContent('<h2 align="center">Профиль пользователя</h2>');

        $query = \QB::query(
            'SELECT 
                u.id,
                u.snils,
                u.extfile,
                u.group,
                u.rules,
                u.added,
                u.closed,
                u.firstname,
                u.lastname,
                u.fathername,
                u.sex,
                s.sex AS sexR,
                DATE_FORMAT(u.birthday, \'%d.%m.%Y\') AS birthday,
                u.phone,
                u.email,
                u.pedstage,
                u.password,
                u.region,
                r.region AS regionR,
                u.city,
                u.distinct,
                u.address,
                u.distinctrm,
                d.`distinct` AS distinctrmR,
                u.cityrm,
                u.checked,
                u.activated
            FROM 
                uts_users u
            LEFT JOIN
                uts_sex s ON s.id = u.sex
            LEFT JOIN
                uts_regions r ON r.id = u.region
            LEFT JOIN
                uts_distinct d ON d.id = u.distinctrm
            WHERE
                u.id = ' . $ID);


        $user = $query->first();
        Error::pdump($user, 'info user');
        $html = '<h3 id="userinfo">Данные пользователя</h3>';//. '<span id="userinfoblock">';

        $html .= '<div id="human" class="formrow shadow round5border dgraybg w100ps">'
            . '<h3>Информация о пользователе' . ' <nobr>[ ID: ' . $user->id . ' ]</nobr></h3>'

            . $this->copyBtn($user->lastname . ' ' . $user->firstname . ' ' . $user->fathername) . '&nbsp;Ф.И.О.: '
            . '<div style="display: inline-block"><strong>' . $user->lastname . '</strong></div> '
            . '<div style="display: inline-block"><strong>' . $user->firstname . '</strong></div> '
            . '<div style="display: inline-block"><strong>' . $user->fathername . '</strong></div>'
            . ' <i class="fa-light fa-' . ($user->sex == 1 ? 'mars' : 'venus') . ' show-tooltip" title="' . $user->sex . '"></i>'
            . '<br />' . $this->copyBtn($user->birthday) . '&nbsp;Дата рождения: <strong>' . $user->birthday . '</strong>'
            . '<br />' . $this->copyBtn($user->snils) . '&nbsp;СНИЛС: <strong>' . $user->snils . '</strong>'
            . '<br />' . $this->copyBtn($user->regionR) . '&nbsp;Регион проживания: <strong>' . $user->regionR . '</strong>'
            . (!is_null($user->distinctrm) ? '<br />' . $this->copyBtn($user->distinctrmR) . '&nbsp;Район: <strong>'
                . $user->distinctrmR . '</strong>' : '') . ($user->cityrm == 1 ? ' [<small>Город</small>]' : '')
            . (!is_null($user->distinct) ? '<br />' . $this->copyBtn($user->distinct) . '&nbsp;Район: <strong>'
                . $user->distinct . '</strong>' : '')
            . '<br />' . $this->copyBtn($user->city) . '&nbsp;Населенный пункт: <strong>' . $user->city . '</strong>'
            . '<br />' . $this->copyBtn($user->address) . '&nbsp;Ул., дом, кв.: <strong>' . $user->address . '</strong>'
            . '<br />' . $this->copyBtn($user->phone) . '&nbsp;Телефон: <strong>' . $user->phone . '</strong>'
            . '<br />' . $this->copyBtn($user->email) . '&nbsp;E-mail: <strong>' . $user->email . '</strong>'
            . '<br />' . $this->copyBtn($user->pedstage) . '&nbsp;Педстаж: <strong>' . $user->pedstage . '</strong>'
            . '<br><span' . (!is_null($user->extfile) ? ' class="scandoc"><a href="/?act=download&file=snils_'
                . $user->id . '.' . $user->extfile . '"  target="_blank" title="Скан СНИЛС"><img src="/img/snils.png"></a>' :
                '> <span style="color: red">Скан СНИЛС не загружен</span>')
            . ($user->checked == 1 ? '<i class="fa-light fa-badge-check fa-2x good" title="Данные подтверждены"></i>' : '') . '</span>';
        $html .= '</div>';

        $query = \QB::table('work')->select(
            \QB::raw(\OnlineRecord\config['prefix'] . 'work.id as workid'), 'work.parent', 'work.organisation', 'work.waddress', 'work.profession', 'work.stage',
            'work.region', \QB::raw(\OnlineRecord\config['prefix'] . 'regions.region as regionR'), 'work.distinctrm', \QB::raw(\OnlineRecord\config['prefix'] . 'distinct.distinct as distinctR'),
            'work.city', 'work.gosslujba', 'work.phone', 'work.checked'
        )
            ->leftJoin('regions', 'regions.id', '=', 'work.region')
            ->leftJoin('distinct', 'distinct.id', '=', 'work.distinctrm')
            ->where('parent', '=', $ID);
        $job = $query->get();

        Error::pdump($user, 'info user');

        $html .= '<h3>Информация о месте работы</h3>';
        if (count($job) != 0) {
            foreach ($job as $v) {
                $html .= '<div class="formrow shadow round5border dgraybg w100ps margin5updown' . ($v->checked == 1 ? ' ochecked' : '') . '">'
                    . ($v->gosslujba == 1 ? '[<small style="color: green">Является госслужащим</small>]' : '') .
                    $this->copyBtn($v->organisation) . '&nbsp;Организация: <strong>' . $v->organisation . '</strong>' .
                    '<br />' . $this->copyBtn($v->waddress) . '&nbsp;Почтовый адрес: <strong>' . $v->waddress . '</strong>' .
                    '<br />' . $this->copyBtn($v->city) . '&nbsp;Населенный пункт: <strong>' . $v->city . '</strong>' .
                    ($v->region == 13 ? '<br />' . $this->copyBtn($v->distinctR) . '&nbsp;Район РМ: <strong>' . $v->distinctR . '</strong>' : '') .
                    '<br />' . $this->copyBtn($v->regionR) . '&nbsp;Регион: <strong>' . $v->regionR . '</strong>' .
                    '<br />' . $this->copyBtn($v->profession) . '&nbsp;Должность: <strong>' . $v->profession . '</strong>' .
                    '<br />' . $this->copyBtn($v->stage) . '&nbsp;Стаж в должности: <strong>' . $v->stage . '</strong>' .
                    '<br />' . $this->copyBtn($v->phone) . '&nbsp;Рабочий телефон: <strong>' . $v->phone . '</strong>';
                //Error::pdump($v);
                $html .= '</div>';
            }
        }

        $query = \QB::table('pass')->select(array(
            'pass.id', \QB::raw(\OnlineRecord\config['prefix'] . 'pass.citizenship as citizen'), 'pass.series', 'pass.number',
            \QB::raw("DATE_FORMAT(" . \OnlineRecord\config['prefix'] . "pass.datedoc,'%d.%m.%Y') as datedoc"), 'pass.info',
            'pass.extfile', 'pass.parent', 'pass.checked', 'citizenship.citizenship'))
            ->leftJoin('citizenship', 'citizenship.id', '=', 'pass.citizenship')
            ->where('pass.parent', '=', $ID);
        $pass = $query->get();

        $html .= '<h3 id="passheader">Паспортов в системе: ' . count($pass) . '</h3>';
        if (count($pass) != 0) {
            foreach ($pass as $v) {
                $html .= '<div class="formrow shadow round5border dgraybg w100ps margin5updown' . ($v->checked == 1 ? ' ochecked' : '') . '">' .
                    $this->copyBtn($v->citizenship) . '&nbsp;Гражданство: <strong>' . $v->citizenship . '</strong>' .
                    '<br />' . $this->copyBtn($v->series . '-' . $v->number) . '&nbsp;Номер: <strong>' . $v->series . '-' . $v->number . '</strong>' .
                    '<br />' . $this->copyBtn($v->info) . '&nbsp;Выдан:     <strong>' . $v->info . '</strong>' .
                    '<br />' . $this->copyBtn($v->datedoc) . '&nbsp;Дата выдачи: <strong>' . $v->datedoc . '</strong> ' .
                    '<br><span'
                    . (!is_null($v->extfile) ? ' class="scandoc"><a href="/?act=download&file=pass_' . $v->id . '.' . $v->extfile
                        . '"  target="_blank" title="Скан паспорта"><img src="/img/passport.png"></a>' :
                        '> <span style="color: red">Скан паспорта не загружен</span>')
                    . '</span>';
                $html .= '</div>';
            }
        }

        $query = \QB::table('diplom')->select(array(
            'diplom.id', 'diplom.edu_level', 'diplom.almamatter', 'education_level.level', 'diplom.series', 'diplom.number', 'diplom.regnumber',
            \QB::raw("DATE_FORMAT(" . \OnlineRecord\config['prefix'] . "diplom.datedoc,'%d.%m.%Y') as datedoc"), 'diplom.qualification',
            'diplom.stepen', 'diplom.zvanie', 'diplom.f', 'diplom.i', 'diplom.o',
            'diplom.parent', 'diplom.dextfile', 'diplom.fextfile', 'diplom.checked'))
            ->leftJoin('education_level', 'education_level.id', '=', 'diplom.edu_level')
            ->where('parent', '=', $ID);
        $diplom = $query->get();

        $html .= '<h3>Дипломов в системе: ' . count($diplom) . '</h3>';
        if (count($diplom) != 0) {
            foreach ($diplom as $v) {
                $html .= '<div class="formrow shadow round5border dgraybg w100ps margin5updown' . ($v->checked == 1 ? ' ochecked' : '') . '">'
                    . $this->copyBtn($v->level) . '&nbsp;Уровень образования: <strong>' . $v->level . '</strong>' .
                    '<br />' . $this->copyBtn($v->almamatter) . '&nbsp;ВУЗ, ССУЗ: <strong>' . $v->almamatter . '</strong>' .
                    '<br />' . $this->copyBtn($v->series . '-' . $v->number) . '&nbsp;Номер диплома: <strong>' . $v->series . '-' . $v->number . '</strong>' .
                    '<br />' . $this->copyBtn($v->regnumber) . '&nbsp;Рег. номер: <strong>' . $v->regnumber . '</strong>' .
                    '<br />' . $this->copyBtn($v->datedoc) . '&nbsp;Выдан: <strong>' . $v->datedoc . '</strong>' .
                    '<br />' . $this->copyBtn($v->qualification) . '&nbsp;Квалификация: <strong>' . $v->qualification . '</strong>' .
                    '<br />' . $this->copyBtn($v->stepen) . '&nbsp;Степень: <strong>' . $v->stepen . '</strong>' .
                    '<br />' . $this->copyBtn($v->zvanie) . '&nbsp;Звание: <strong>' . $v->zvanie . '</strong>' .
                    ((!is_null($v->f) || !is_null($v->i) || !is_null($v->o)) ? '<br />' . $this->copyBtn($v->f . ' ' . $v->i . ' ' . $v->o)
                        . '&nbsp;Ф.И.О. в дипломе: <strong>' . $v->f . ' ' . $v->i . ' ' . $v->o . '</strong>' : '') .
                    '<br><span'
                    . (!is_null($v->dextfile) ? ' class="scandoc"><a href="/?act=download&file=diplom_' . $v->id . '.' . $v->dextfile
                        . '"  target="_blank" title="Скан диплома"><img src="/img/diplom.png"></a>' : '> '
                        . '<span style="color: red">Скан диплома не загружен</span>')
                    . '</span>' .
                    ((!is_null($v->f) || !is_null($v->i) || !is_null($v->o))
                        ? (!is_null($v->fextfile)
                            ? '<span class="scandoc"><a href="/?act=download&file=fio_' . $v->id . '.' . $v->fextfile . '"  '
                            . 'target="_blank" title="Скан документа о смене ФИО"><img src="/img/fio.png"></a></span>'
                            : '<span><span style="color: red">Скан документа о смене ФИО не загружен</span>'
                        )
                        : ''
                    );

                $html .= '</div>';
            }

            $html .= '</div>';
            //$this->addJQCode('$("#userinfo").on("click", function(){ $(this).parent().attr("class", ""); $("#userinfoblock").toggle();});');
            $this->addContent($html);
        }
    }

    //========================================================================================================================================
    public static function encrypt($text)
    {
        $ivlen = openssl_cipher_iv_length($cipher = "AES-128-CBC");
        $iv = openssl_random_pseudo_bytes($ivlen);
        $ciphertext_raw = openssl_encrypt($text, $cipher, ENCRYPTION_KEY, $options = OPENSSL_RAW_DATA, $iv);
        $hmac = hash_hmac('sha256', $ciphertext_raw, ENCRYPTION_KEY, $as_binary = true);
        $ciphertext = base64_encode($iv . $hmac . $ciphertext_raw);
        return $ciphertext;
    }

    //========================================================================================================================================
    public static function decrypt($text)
    {
        $c = base64_decode($text);
        $ivlen = openssl_cipher_iv_length($cipher = "AES-128-CBC");
        $iv = substr($c, 0, $ivlen);
        $hmac = substr($c, $ivlen, $sha2len = 32);
        $ciphertext_raw = substr($c, $ivlen + $sha2len);
        $plaintext = openssl_decrypt($ciphertext_raw, $cipher, ENCRYPTION_KEY, $options = OPENSSL_RAW_DATA, $iv);
        $calcmac = hash_hmac('sha256', $ciphertext_raw, ENCRYPTION_KEY, $as_binary = true);
        if (hash_equals($hmac, $calcmac)) {
            return $plaintext;
        }
    }

    //=========================================================================================================================================
    public function sendMail($address, $subject, $body, $okMessage)
    {
        //return;
        $mail = new PHPMailer(true);

        try {
            //Server settings
            $mail->setLanguage('ru');
            $mail->SMTPDebug = SMTP::DEBUG_OFF;                      //Enable verbose debug output
            $mail->isSMTP();                                            //Send using SMTP
            $mail->Host = 'smtp.yandex.ru';                     //Set the SMTP server to send through
            $mail->SMTPAuth = true;                                   //Enable SMTP authentication
            $mail->Username = 'no-reply.cnppm';                     //SMTP username
            $mail->Password = 'mlrnhjvnkhckokau';                               //SMTP password
            /*$mail->Username   = 'noreply.cnppm';                     //SMTP username
                $mail->Password   = 'goazktgqhtegjwhc';*/
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;            //Enable implicit TLS encryption
            $mail->Port = 465;                                    //TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`
            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            //Recipients
            $mail->setFrom('no-reply.cnppm@yandex.ru', 'ЦНППМ "Педагог 13.ру"');
            $mail->addAddress(trim($address));     //Add a recipient

            //Content
            $mail->isHTML(true);                                  //Set email format to HTML
            $mail->Subject = $subject;
            $mail->Body = '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />' . $body;
            $mail->send();
            $this->addContent($okMessage);
        } catch (Exception $e) {
            $this->setError('Не удалось отправить письмо.<br />' . $mail->ErrorInfo);
        }
    }

    //===============================================================================================================================
    function tree($a, $id = null, $level = 0)
    {
        $html = '<ul>';
        foreach ($a as $v) {
            if ($v->parent === $id) {
                $html .= '<li>'
                    . ($v->count != 0 ? '<a href="/index.php?act=show&cat=' . $v->id . '">' : '') .
                    '<span ' . ($id === null ? 'class="rootcat"' : '') . '>' . $v->name . '</span>'
                    . ($v->count != 0 ? '</a> &mdash; ' . $v->count . number($v->count, array(' курс', ' курса', ' курсов')) : '')
                    . $this->tree($a, $v->id, $level + 1) . '</li>';
            }
        }
        $html .= '</ul>';
        return $html;
    }

    //===============================================================================================================================
    function admTree($a, $id = null, $level = 0)
    {
        $html = '<ul>';
        foreach ($a as $v) {
            if ($v->parent === $id) {
                $html .= '<li>'
                    . ($v->count != 0 ? '<a href="/index.php?act=show&cat=' . $v->id . '">' : '') .
                    '<span ' . ($id === null ? 'class="rootcat"' : '') . '>' . $v->name . '</span>'
                    . ($v->count != 0 ? '</a> &mdash; ' . $v->count . number($v->count, array('&nbsp;курс', '&nbsp;курса', '&nbsp;курсов')) : '');

                if ($this->isRule(RULE_CATEGORY)) {
                    $html .= ' <a href="#modal1" title="Переименовать категорию" class="open_modal edit_cat" data-name="' . $v->name
                        . '" data-id="' . $v->id . '" data-param="renamecat"><span class="fa-light fa-pen-to-square"></span></a>'

                        . ' <a href="#modal1" title="Добавить подкатегорию"  class="open_modal edit_cat" data-id="' . $v->id
                        . '" data-param="addsubcat"><i class="fa-light fa-share-from-square fa-flip-both"></i></a>'

                        . ' <a href="/?act=delcat&id=' . $v->id . '" title="Удалить категорию" '
                        . 'onClick="if(window.confirm(\'ВНИМАНИЕ!\n\nКатегория будет удалена со всеми подкатегориями и со всеми вложенными '
                        . 'курсами!\n\nОтмена данного действия будет НЕВОЗМОЖНА!\n\nЕсли вы хотите сохранить подкатегории и курсы, сначала '
                        . 'перенесите их в другие категории!\n\nВы уверены, что хотите удалить категорию\n&laquo;' . $v->name . '&raquo;?\')==true) '
                        . '{return true;} else {return false;}"><i class="fa-light fa-trash-can"></i></a>&nbsp;&nbsp;&nbsp;';
                }

                if ($this->isRule(RULE_MODERATE)) {
                    $html .= ' <a href="/?act=addcourse&cat=' . $v->id . '" title="Добавить курс" ><i class="fa-light fa-plus"></i></a>';
                }

                $html .= $this->admTree($a, $v->id, $level + 1) . '</li>';
            }
        }
        $html .= '</ul>';
        return $html;
    }

    //=====================================================================================================================================
    public function fileDownload($file)
    {
        Error::pdump('Начинаем качать');
        if (!$this->isAuthorized()) {
            $this->setError('Вы не авторизованы! Сначала <a href="/?act=loginform">войдите под своей учетной записью</a>!');
            return false;
        }
        Error::pdump('Закачка авторизована');
        /* if (!in_array($file, $this->files)){
            $this->setError('Ай-ай-ай, как некрасиво >:(');
                return false;
        }*/
        Error::pdump('Файл наш');
        Error::pdump('файл - ' . uploaddir . $file);
        if (file_exists(uploaddir . $file)) {
            Error::pdump('файл существует ' . uploaddir . $file);
            // сбрасываем буфер вывода PHP, чтобы избежать переполнения памяти выделенной под скрипт
            // если этого не сделать файл будет читаться в память полностью!
            if (ob_get_level()) {
                ob_end_clean();
            }
            // заставляем браузер показать окно сохранения файла
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename=' . $this->user->lastname . '_' . $this->user->firstname . '_' . $this->user->fathername . '-' . $file);
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize(uploaddir . $file));
            // читаем файл и отправляем его пользователю
            readfile(uploaddir . $file);
            exit;
        }
    }

    //========================================================================================================================================
    public function uploadImage()
    {
        if (!$this->isAuthorized() || !$this->isRule(RULE_MODERATE)) {
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

        reset($_FILES);
        $temp = current($_FILES);
        if (is_uploaded_file($temp['tmp_name'])) {
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
            $filetowrite = $imageFolder . date('d-m-Y_H-i_') . \OnlineRecord\RusToLat($temp['name']);
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

    /** ============================================================================================
     * возвращает страницу со списком пользователей
     * @return string
     */
    public function getUserList(array $param = ['type' => 'last']): string
    {
        switch ($param['type']) {
            case 'search':
                return $this->searchUser($param['text'], $param['mode'], $param['group']);
                break;

            case 'last':
            default:
                return $this->getLastUsers($param['mode'], $param['group']);
                break;
        }

    }

    /** ============================================================================================
     * возвращает страницу с системными настройками
     * @return string
     */
    private function getSystemSettings(): string
    {
        $this->addContent('<p>Админкама</p>');
        //============== получаем общие настройки ====================================================
        $query = \QB::table('settings_type');
        $result = $query->get();
        $html = '<ul id="admmenu">';
        foreach ($result as $k => $v) {
            $html .= '<li><span class="showsettings" data-type="' . $v->id . '">' . $v->typename . '</span><span class="closeset">X</span><div class="adm"></div></li>';
        }
        $html .= '</ul>';
        $html = '<div class="tabs">
                  <input type="radio" name="tab-btn" id="tab-btn-1" value="" checked>
                  <label for="tab-btn-1">Общие настройки</label>
                  <input type="radio" name="tab-btn" id="tab-btn-2" value="">
                  <label for="tab-btn-2" class="stab" data-id="2" data-cat="moder">Модераторы</label>
                  <input type="radio" name="tab-btn" id="tab-btn-3" value="">
                  <label for="tab-btn-3" class="stab" data-id="3" data-cat="users">Пользователи</label>
                  <div id="content-1">' . $html . '</div>
                  <div id="content-2"></div>
                  <div id="content-3"></div>
                </div>';
        return $html;
    }

    /** ==== ищет пользователя по ФИО и СНИЛС, можно искать несколько, введя значения через запятую
     * @param string $text
     * @return string
     */
    public function searchUser(string $text, $mode = 'html', $group = null): string
    {
        //$html = 'g='.$group.'<br>';
        $searchStrings = explode(",", $text);
        //$mtext = addslashes($text);
        Error::pdump($searchStrings, 'search texts');
        $sql = 'SELECT 
                                    u.id,
                                    u.snils, 
                                    CONCAT(u.lastname," ",u.firstname," ",u.fathername) AS fio,
                                    DATE_FORMAT(u.birthday, "%d.%m.%Y") AS birthday,
                                    u.`group`,
                                    DATE_FORMAT(u.added, "%d.%m.%Y") AS added,
                                    u.email,
                                    u.phone,
                                    u.city,
                                    u.activated,
                                    u.checked,
                                    u.banned,
                                    (SELECT id FROM ' . \OnlineRecord\config['prefix'] . 'work WHERE parent = u.id ORDER BY id DESC LIMIT 1) AS work,
                                    (SELECT id FROM ' . \OnlineRecord\config['prefix'] . 'pass WHERE parent = u.id ORDER BY id DESC LIMIT 1) AS pass,
                                    (SELECT id FROM ' . \OnlineRecord\config['prefix'] . 'diplom WHERE parent = u.id ORDER BY id DESC LIMIT 1) AS diplom,
                                    (SELECT COUNT(*) FROM uts_work w WHERE w.parent = u.id) AS cw,
                                    (SELECT COUNT(*) FROM uts_pass p WHERE p.parent = u.id) AS cp,
                                    (SELECT COUNT(*) FROM uts_diplom dd WHERE dd.parent = u.id) AS cd,
                                    d.f,
                                    d.i,
                                    d.o
                                FROM
                                        uts_users u
                                LEFT JOIN
                                        uts_diplom d ON d.parent = u.id
                                WHERE ';
        if (!is_null($group)) {
            $sql .= ' u.group = ' . filter_var($group, FILTER_SANITIZE_NUMBER_INT) . ' AND ';
        }
        $sc = count($searchStrings);
        foreach ($searchStrings as $k => $v) {
            $sql .= '(u.firstname LIKE  "%' . addslashes(trim($v)) . '%" OR
                                      u.lastname LIKE  "%' . addslashes(trim($v)) . '%" OR
                                      u.fathername LIKE  "%' . addslashes(trim($v)) . '%" OR
                                      u.snils LIKE  "%' . addslashes(trim($v)) . '%" OR
                                      u.phone LIKE  "%' . addslashes(trim($v)) . '%" OR
                                      u.email LIKE  "%' . addslashes(trim($v)) . '%")';
            if ($k != $sc - 1) $sql .= ' OR ';
        }
        $sql .= ' ORDER BY id DESC 
                                LIMIT ' . Settings::getSetting('limitUserList');
        Error::pdump($sql, 'sql--------');
        $users = \QB::query($sql)->get();
        Error::pdump($users, 'users----------');
        //$users = $query->get();
        switch ($mode) {
            case 'json':
                return json_encode($users, JSON_UNESCAPED_UNICODE);
                break;
            case 'html':
            default:
                $showAdmCtrl = !(bool)($_REQUEST['hideadm'] ?? false);
                //$queryObj = $query->getQuery()->getRawSql();
                Error::pdump($users, 'search users');
                $html = '<form action="/?act=admin&cat" method="post" enctype="multipart/form-data">' .
                    '<ul class="userinfoblock">' .
                    '<ul class="userinfo th">' .
                    '<li><input type="checkbox" id="selall"></li>' .
                    '<li id="sortid" class="sortth">ID</li>' .
                    ($this->isRule(RULE_ADMIN) && $showAdmCtrl ? '<li>Группа</li><li><i class="fa-light fa-key" title="Сбросить пароль"></i></li>' : '') .
                    '<li id="sortfio" class="sortth">Ф.И.О. <i class="fa-light fa-arrow-down-short-wide"></i></li>' .
                    '<li id="sortemail" class="sortth">email <i class="fa-light fa-arrow-down-short-wide"></i></li>' .
                    '<li id="sortphone" class="sortth">Тел. <i class="fa-light fa-phone"></i></li>' .
                    '<li id="sortsnils" class="sortth">СНИЛС <i class="fa-light fa-arrow-down-short-wide"></i></li>' .
                    ($showAdmCtrl ? '<li><i class="fa-light fa-user-check orange" title="Активирован"></i></li>' .
                        '<li><i class="fa-light fa-check-to-slot" title="Данные проверены"></i></li>' .
                        '<li><i class="fa-light fa-ban wrong" title="Заблокирван"></i></li>' .
                        '<li><i class="fa-light fa-people-arrows" title="Диплом на другую фамилию"></i></li>' : '') .
                    '</ul>';
                $groups = $this->getGroupList();
                foreach ($users as $v) {
                    $docsOk = $v->cw > 0 && $v->cp > 0 && $v->cd > 0;
                    $pdr = 0 | ($v->cw > 0 ? 1 : 0) | ($v->cd > 0 ? 2 : 0) | ($v->cp > 0 ? 4 : 0);
                    $html .= '<ul class="userinfo ' . ($v->banned ? 'wrong' : ($v->activated ? '' : 'orange')) . '">' .
                        '<li><input type="checkbox" name="user[' . $v->id . ']" ' . (!$docsOk ? 'disabled' : '') . ' value="' . $v->id . '">' .
                        '<input type="hidden" name="pass[' . $v->id . ']" value="' . $v->pass . '">' .
                        '<input type="hidden" name="diplom[' . $v->id . ']" value="' . $v->diplom . '">' .
                        '<input type="hidden" name="work[' . $v->id . ']" value="' . $v->work . '">' .
                        '</li>' .
                        '<li class="id">' . $v->id . '</li>' .
                        ($this->isRule(RULE_ADMIN) && $showAdmCtrl ? '<li>' . $this->htmlGroupList($groups, $v->group, $v->id) . '</li>' .
                            '<li><a href="#" class="resetpass" data-snils="' . $v->snils . '" data-email="' . $v->email . '" data-phone="' . $v->phone . '"><i class="fa-light fa-key"></i></a></li>' : '') .
                        '<li class="fio ' . (!$docsOk ? 'coral' : '') . '" ' . (!$docsOk ? 'title="[' . $v->added . ', ' . $v->city . ']Не все документы заполнены! П:' . $v->cp . ';Д:' . $v->cd . ';Р:' . $v->cw . '"' : 'title="Дата регистрации ' . $v->added . ', ' . $v->city . '"') . '><img src="/img/pdr' . $pdr . '.png"> ' .
                        '<a href="/?act=uprofile&id=' . $v->id . '">' . $v->fio . '</a>' .
                        ' <small>[' . $v->birthday . ']</small></li>' .
                        '<li class="email">' . $v->email . '</li>' .
                        '<li class="phone">' . $v->phone . '</li>' .
                        '<li class="snils">' . $v->snils . '</li>' .
                        ($showAdmCtrl ? '<li><input type="checkbox" name="activation" class="act" value="' . $v->id . '" title="Активирован" ' . ($v->activated ? 'checked' : '') . ' ' . (!$this->isRule(RULE_MODERATE) ? 'disabled' : '') . '></li>' .
                            '<li><input type="checkbox" class="check" value="' . $v->id . '" title="Данные проверены" ' . ($v->checked ? 'checked' : '') . ' disabled></li>' .
                            '<li><input type="checkbox" name="ban" class="ban" value="' . $v->id . '" title="Забанить/разбанить" ' . ($v->banned ? 'checked' : '') . ' ' . (!$this->isRule(RULE_ADMIN) ? 'disabled' : '') . ' >' .
                            '</li>' .
                            '<li><input type="checkbox" class="fioch" value="' . $v->id . '" title="Сменил(а) ФИО" ' . (!(is_null($v->f) && is_null($v->i) && is_null($v->i)) ? 'checked' : '') . ' disabled></li>' : '') .
                        '</ul>';
                }
                $html .= '</ul></form>';
                //if (DEBUG) $html .= '<div class="debug">'.$queryObj.'</div>';
                return $html;
                break;
        }

    }

    /** возвращает список последних зарегистрировавшихся пользователей
     * @return string
     */
    public function getLastUsers($mode = 'html', $group = null): string
    {
        if ($this->isRule(RULE_ADMIN)) {
            $query = \QB::query('SELECT COUNT(id) AS `count`, (SELECT COUNT(id) FROM ' . \OnlineRecord\config['prefix'] . 'users WHERE added >= CURRENT_DATE()) AS tcount FROM ' . \OnlineRecord\config['prefix'] . 'users WHERE `group` = 4');
            $uc = $query->get();
            Error::pdump($uc, 'UC ');
        }
        $query = \QB::query('SELECT
                                    u.id,
                                    u.snils,
                                    CONCAT(u.lastname," ",u.firstname," ",u.fathername) AS fio,
                                    DATE_FORMAT(u.birthday, "%d.%m.%Y") AS birthday,
                                    g.`group` AS gname,
                                    u.group,
                                    u.rules | g.rules AS rules,
                                    DATE_FORMAT(u.added, "%d.%m.%Y") AS added,
                                    u.email,
                                    u.phone,
                                    u.checked,
                                    u.activated,
                                    u.banned,
                                    u.city,
                                    d.f,
                                    d.i,
                                    d.o,
                                    (SELECT id FROM ' . \OnlineRecord\config['prefix'] . 'work WHERE parent = u.id ORDER BY id DESC LIMIT 1) AS work,
                                    (SELECT id FROM ' . \OnlineRecord\config['prefix'] . 'pass WHERE parent = u.id ORDER BY id DESC LIMIT 1) AS pass,
                                    (SELECT id FROM ' . \OnlineRecord\config['prefix'] . 'diplom WHERE parent = u.id ORDER BY id DESC LIMIT 1) AS diplom,
                                    (SELECT COUNT(*) FROM ' . \OnlineRecord\config['prefix'] . 'work WHERE parent = u.id) AS cw,
                                    (SELECT COUNT(*) FROM ' . \OnlineRecord\config['prefix'] . 'pass WHERE parent = u.id) AS cp,
                                    (SELECT COUNT(*) FROM ' . \OnlineRecord\config['prefix'] . 'diplom WHERE parent = u.id) AS cd
                                FROM
                                        ' . \OnlineRecord\config['prefix'] . 'users u
                                LEFT JOIN
                                        ' . \OnlineRecord\config['prefix'] . 'ugroup g ON g.id = u.`group`
                                LEFT JOIN
                                        ' . \OnlineRecord\config['prefix'] . 'diplom d ON d.parent = u.id' .
            (!is_null($group) ? ' WHERE u.group = ' . filter_var($group, FILTER_SANITIZE_NUMBER_INT) : '') .
            ' ORDER BY id DESC 
              LIMIT ' . Settings::getSetting('limitUserList'));

        $users = $query->get();
        Error::pdump($users, 'user list---');
        switch ($mode) {
            case 'json':
                return json_encode($users, JSON_UNESCAPED_UNICODE);
                break;

            case 'html':
            default:
                $showAdmCtrl = !(bool)($_REQUEST['hideadm'] ?? false);
                Error::pdump($showAdmCtrl, 'adm');
                $html = '' . ($this->isRule(RULE_ADMIN) ? '<p>Всего пользовтелей - ' . $uc[0]->count . ' (+' . $uc[0]->tcount . ' сегодня)<br />' : '');
                $html .= 'Последние зарегистрированные пользователи:</p>';
                //$html .= '<script src="/inc/getLastUsers.js"></script>';
                $html .= '<form action="/?act=admin&cat" method="post" enctype="multipart/form-data">' .
                    '<ul class="userinfoblock">' .
                    '<ul class="userinfo th">' .
                    '<li><input type="checkbox" id="selall"></li>' .
                    '<li id="sortid" class="sortth">ID</li>' .
                    ($this->isRule(RULE_ADMIN) && $showAdmCtrl ? '<li>Группа</li><li><i class="fa-light fa-key" title="Сбросить пароль"></i></li>' : '') .
                    ($this->isRule(RULE_MODERATE) ? '<li><i class="fa-sharp fa-light fa-memo-circle-check" title="Скачать все документы"></i></li>' : '') .
                    '<li id="sortfio" class="sortth">Ф.И.О. <i class="fa-light fa-arrow-down-short-wide"></i></li>' .
                    '<li id="sortemail" class="sortth">email <i class="fa-light fa-arrow-down-short-wide"></i></li>' .
                    '<li id="sortphone" class="sortth">Тел. <i class="fa-light fa-phone"></i></li>' .
                    '<li id="sortsnils" class="sortth">СНИЛС <i class="fa-light fa-arrow-down-short-wide"></i></li>' .
                    ($showAdmCtrl ? '<li><i class="fa-light fa-user-check orange" title="Активирован"></i></li>' .
                        '<li><i class="fa-light fa-check-to-slot" title="Данные проверены"></i></li>' .
                        '<li><i class="fa-light fa-ban wrong" title="Заблокирван"></i></li>' .
                        '<li><i class="fa-light fa-people-arrows" title="Диплом на другую фамилию"></i></li>' : '') .
                    '</ul>';
                $groups = $this->getGroupList();
                //Error::pdump($groups, 'goups-');
                foreach ($users as $v) {
                    $docsOk = $v->cw > 0 && $v->cp > 0 && $v->cd > 0;
                    $pdr = 0 | ($v->cw > 0 ? 1 : 0) | ($v->cd > 0 ? 2 : 0) | ($v->cp > 0 ? 4 : 0);
                    $html .= '<ul class="userinfo ' . ($v->banned ? 'wrong' : ($v->activated ? '' : 'orange')) . '">' .
                        '<li><input type="checkbox" name="user[' . $v->id . ']" ' . (!$docsOk ? 'disabled' : '') . ' value="' . $v->id . '">' .
                        '<input type="hidden" name="pass[' . $v->id . ']" value="' . $v->pass . '">' .
                        '<input type="hidden" name="diplom[' . $v->id . ']" value="' . $v->diplom . '">' .
                        '<input type="hidden" name="work[' . $v->id . ']" value="' . $v->work . '">' .
                        '</li>' .
                        '<li class="id">' . $v->id . '</li>' .
                        ($this->isRule(RULE_ADMIN) && $showAdmCtrl ? '<li>' . $this->htmlGroupList($groups, $v->group, $v->id) . '</li>' .
                            '<li><a href="#" class="resetpass" data-snils="' . $v->snils . '" data-email="' . $v->email . '" data-phone="' . $v->phone . '"><i class="fa-light fa-key"></i></a></li>' : '') .
                        ($this->isRule(RULE_MODERATE) ? '<li><i class="fa-sharp fa-light fa-memo-circle-check" title="Скачать все документы"></i></li>' : '') .
                        '<li class="fio ' . (!$docsOk ? 'coral' : '') . '" ' . (!$docsOk ? 'title="[' . $v->added . ', ' . $v->city . ']Не все документы заполнены! П:' . $v->cp . ';Д:' . $v->cd . ';Р:' . $v->cw . '"' : 'title="Дата регистрации ' . $v->added . ', ' . $v->city . '"') . '><img src="/img/pdr' . $pdr . '.png"> ' .
                        '<a href="/?act=uprofile&id=' . $v->id . '">' . $v->fio . '</a>' .
                        ' <small>[' . $v->birthday . ']</small></li>' .
                        '<li class="email"><small>' . $v->email . '</small></li>' .
                        '<li class="phone"><small>' . $v->phone . '</small></li>' .
                        '<li class="snils"><small><nobr>' . $v->snils . '</nobr></small></li>' .
                        ($showAdmCtrl ? '<li><input type="checkbox" name="activation" class="act" value="' . $v->id . '" title="Активирован" ' . ($v->activated ? 'checked' : '') . ' ' . (!$this->isRule(RULE_MODERATE) ? 'disabled' : '') . '></li>' .
                            '<li><input type="checkbox" class="check" value="' . $v->id . '" title="Данные проверены" ' . ($v->checked ? 'checked' : '') . ' disabled></li>' .
                            '<li><input type="checkbox" name="ban" class="ban" value="' . $v->id . '" title="Забанить/разбанить" ' . ($v->banned ? 'checked' : '') . ' ' . (!$this->isRule(RULE_ADMIN) ? 'disabled' : '') . ' >' .
                            '</li>' .
                            '<li><input type="checkbox" class="fioch" value="' . $v->id . '" title="Сменил(а) ФИО" ' . (!(is_null($v->f) && is_null($v->i) && is_null($v->i)) ? 'checked' : '') . ' disabled></li>' : '') .
                        '</ul>';
                }
                $html .= '</ul>' .
                    '</form>';
                return $html;
                break;
        }
    }

    public function changeUserGroup(int $user, int $group): bool
    {
        if (!$this->isAuthorized() || !$this->isRule(RULE_ADMIN)) {
            return false;
        }
        $data = array('group' => $group);
        $query = \QB::table('users')->where('id', $user)->update($data);
        return true;
    }

    /** проверяет существование юзера с определенным e-mail ========================================
     * @param string $email - адрес
     * @return bool
     */
    public function mailExist(string $email): bool
    {
        $query = \QB::table('users')->where('email', $email);
        Error::pdump($query);
        if ($query->count() != 0) {
            return true;
        } else {
            return false;
        }
    }

    /** возвращает форму поиска пользователя ========================================================================
     * @return string
     */
    public function getSearchUserForm(): string
    {
        return file_get_contents(dirname(__FILE__) . '/searchUser.html');
    }

    /** возвращает ассоциативный массив групп пользователей =========================================================
     * @return array
     */
    private function getGroupList(): array
    {
        $query = \QB::table('ugroup')->get();
        //Error::pdump($query, 'group list');
        return $query;
    }

    /** возвращает html select со списком групп =====================================================================
     * @param array $group - массив со списком групп [id, group, rules]
     * @param string $sel - value выбранного пункта
     * @param int|null $data - данные, записываемые в параметр data-user тега select
     * @return string
     */
    private function htmlGroupList(array $group, string $sel, int $data = null): string
    {
        $html = '<select class="group"' . (!is_null($data) ? ' data-user="' . $data . '"' : '') . '>';
        foreach ($group as $v) {
            $html .= '<option value="' . $v->id . '" ' . ($sel == (string)$v->id ? 'selected' : '') . '>' . $v->group . '</option>';
        }
        $html .= '</select>';
        //Error::pdump($html, 'list groups');
        return $html;
    }

    /** ghjcnj pfukeirf lkz dczrjq abuyb
     * @param $param
     * @return void
     */
    public function test($param = null)
    {
        if (!isset($param['get'])) {
            $this->addHeader('<link rel="stylesheet" href="inc/select2.css">');
            $this->addHeader('<script src="inc/select2.full.js"></script>');
            $this->addContent(file_get_contents(dirname(__FILE__) . '/reports/constructor.html'));
        } else {
            $Con = Constructor::getFieldsList($param['tables']);
        }
    }
}