<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: *");

require 'vendor/autoload.php';

use OnlineRecord\Error;
use OnlineRecord\Pager;

// !FIXME - данные в базе. При изменениях в базе исправить и тут
const APP_STATUS_NEW = 1;//             На рассмотрении
const APP_STATUS_ACCEPTED = 2;//	Зачислен на курс
const APP_STATUS_REJECTED = 3;//	Отклонена
const APP_STATUS_ENDCOURSE = 4;//	Курс окончен
const APP_STATUS_REVOKED = 5;//         Отозвана заявителем
const APP_STATUS_EXPULSION = 6;//	Отчислен


Error::init();
Error::pdump((isset($_COOKIE['DEBUG']) ? 'Debug ON' : 'DebugOFF'));
if (count($_REQUEST) != 0) Error::pdump($_REQUEST, '$_REQUEST');
if (count($_FILES) != 0) Error::pdump($_FILES, '$_FILES');

    $lk = new \OnlineRecord\LK(); 
    
    if (isset($_COOKIE['onlineRecord'])){
        $c = $lk->decrypt($_COOKIE['onlineRecord']);
        $c = unserialize($c);
        if ($lk->auth($c['user'], $c['pass']))
            $lk->getUserInfo();
    }
    
    if (!isset($_REQUEST['act']))
            $_REQUEST['act'] = 'show';
    
    switch ($_REQUEST['act']) {
        case 'regform':
            $lk->regForm();
            break;

        case 'register':
            $lk->register();
            break;
     
        case 'auth':
            if ($lk->auth()) ;
                $lk->getUserInfo();
            break;
        
        case 'logout':
            $lk->logout();
            break;

        case 'recpassform':
            $lk->lostPassForm();
            break;
        
        case 'lostpass':
            $lk->lostPass();
            break;

        case 'activation':
            $lk->activation($_REQUEST['id']);
            break;

        case 'moderact':
                $lk->moderActivation($_REQUEST['id'], $_REQUEST['action']);
            break;

        case 'ban':
            $lk->moderBan($_REQUEST['id'], $_REQUEST['action']);
            break;

        case 'resetpassform':
            $lk->resetPassForm();
            break;

        case 'resetpass':
            $lk->resetPass();
            break;

        case 'loginform':
            $lk->loginForm();
            break;
        
        case 'showmore':
            $lk->showCourse($_REQUEST['id']);
            break;
        
        case 'profile':
            $lk->profile();
            break;

        case 'uprofile':
            $lk->userProfile($_REQUEST['id']);
            break;

        case 'rectocourse':
            $lk->recToCourse( filter_var($_REQUEST['courseid'], FILTER_SANITIZE_NUMBER_INT),
                              $_REQUEST['coursename'],
                              null,
                              filter_var($_REQUEST['pass'], FILTER_SANITIZE_NUMBER_INT),
                              filter_var($_REQUEST['diplom'], FILTER_SANITIZE_NUMBER_INT),
                              filter_var($_REQUEST['work'], FILTER_SANITIZE_NUMBER_INT),
                              true
                            );
            break;

        case 'recmoretocourse':
                $ucount = count($_REQUEST['user']);
                $wrcount = 0;
                foreach ($_REQUEST['user'] as $k => $v){
                Error::pdump($v.' '.$_REQUEST['pass'][$k].' '.$_REQUEST['diplom'][$k].' '.$_REQUEST['work'][$k], 'appp');
                $query = \QB::table('applications')->where('course', filter_var($_REQUEST['courseid'], FILTER_SANITIZE_NUMBER_INT))->where('student', filter_var($v, FILTER_SANITIZE_NUMBER_INT));
                $result = $query->get();
                if (!$result) {
                    $data = array('course' => filter_var($_REQUEST['courseid'], FILTER_SANITIZE_NUMBER_INT),
                        'student' => filter_var($v, FILTER_SANITIZE_NUMBER_INT),
                        'passport' => filter_var($_REQUEST['pass'][$k], FILTER_SANITIZE_NUMBER_INT),
                        'diplom' => filter_var($_REQUEST['diplom'][$k], FILTER_SANITIZE_NUMBER_INT),
                        'work' => filter_var($_REQUEST['work'][$k], FILTER_SANITIZE_NUMBER_INT)
                    );
                    $insId = \QB::table('applications')->insert($data);
                    Error::pdump($insId, 'id ins');
                    $lk->setAppState($insId, APP_STATUS_ACCEPTED, filter_var($_REQUEST['courseid'], FILTER_SANITIZE_NUMBER_INT));
                } else {
                    $wrcount++;
                }
            }
            if ($wrcount != 0) {
                $lk->setError($wrcount . ' ' . \OnlineRecord\number($wrcount, array('слушатель', 'слушателя', 'слушателей'))
                    . ' уже зачислен' . \OnlineRecord\number($wrcount, array('', 'ы', 'ы'))
                    . ' на этот курс! Эти данные проигнорированы. Остальные зачислены.');
            } else {
                $lk->addRedirect('/?act=group&course='.filter_var($_REQUEST['courseid'], FILTER_SANITIZE_NUMBER_INT),0);
            }
            break;

        case 'delpassport':
            $lk->delPassport($_REQUEST['id']);
            break;
        
        case 'deldiplom':
            $lk->delDiplom($_REQUEST['id']);
            break;
        
        case 'edituser':
            $lk->userInfo($_REQUEST['id']);
            break;
        
        case 'savehuman':
            $lk->saveUser(isset($_REQUEST['moder']) ? true : false);
            break;

        case 'editpassport':
            $lk->passInfo($_REQUEST['id']);
            break;

        case 'savepassport':
            $lk->savePassport(isset($_REQUEST['moder']) ? true : false);
            break;

        case 'editdiplom':
            $lk->diplomInfo($_REQUEST['id']);
            break;

        case 'savediplom':
            $lk->saveDiplom(isset($_REQUEST['moder']) ? true : false);
            break;

        case 'editwork':
            $lk->workInfo($_REQUEST['id']);
            break;
        
        case 'savework':
            $lk->saveWork(isset($_REQUEST['moder']) ? true : false);
            break;

        case 'addpassport':
            $lk->passInfo();
            break;
        
        case 'adddiplom':
            $lk->diplomInfo();
            break;

        case 'addwork':
            $lk->workInfo();
            break;
        
        case 'createcat':
        case 'addsubcat':
            $lk->saveCategory((isset($_REQUEST['id']) ? filter_var($_REQUEST['id'], FILTER_SANITIZE_NUMBER_INT) : 0));
            break;
        
        case 'renamecat':
            $lk->renameCategory((isset($_REQUEST['id']) ? filter_var($_REQUEST['id'], FILTER_SANITIZE_NUMBER_INT) : 0));
            break;

        case 'delcat':
            $lk->deleteCategory((isset($_REQUEST['id']) ? filter_var($_REQUEST['id'], FILTER_SANITIZE_NUMBER_INT) : 0));
            break;

        case 'addnewpredmet':
            $lk->addNewPredmet((isset($_REQUEST['predmet']) ? filter_var($_REQUEST['predmet'], FILTER_SANITIZE_STRING) : 0));
            break;
        
        case 'addcourse':
            $lk->addCourse((isset($_REQUEST['cat']) ? filter_var($_REQUEST['cat'], FILTER_SANITIZE_NUMBER_INT) : 0));
            break;
        
        case 'editcourse':
            $lk->addCourse((isset($_REQUEST['id']) ? filter_var($_REQUEST['id'], FILTER_SANITIZE_NUMBER_INT) : 0), true);
            break;
        
        case 'savecourse':
            $lk->saveCourse((isset($_REQUEST['id']) ? filter_var($_REQUEST['id'], FILTER_SANITIZE_NUMBER_INT) : null));
            break;
        
        case 'reports':
            $lk->getReports(!isset($_REQUEST['id']) ? 0 : $_REQUEST['id']);
            break;

        case 'admin':
            $lk->adminPanel();
            break;
        
        case 'catalogue':
            $lk->catalogue();
            break;
        
        case 'messages':
            $lk->showAllMessages();
            break;
        
        case 'mviewed':
            $lk->setMessageViewed((isset($_REQUEST['id']) ? filter_var($_REQUEST['id'], FILTER_SANITIZE_NUMBER_INT) : 0));
            break;

        case 'download':
            $lk->fileDownload($_REQUEST['file']);
            break;
        
        case 'imageupload':
            $lk->uploadImage();
            exit();
            break;
        
         case 'resendactivationmail':
             if ($lk->mailExist($_REQUEST['email'] ?? ''))
                $lk->sendActivationMail(isset($_REQUEST['email']) ? $_REQUEST['email'] : die());
             else
                 $lk->addContent('Такого пользователя не существует! Для использования сервиса необходима <a href="/?act=regform" class="button">регистрация</a>.');
            break;

        case 'showcourse':
            $lk->showCourse((isset($_REQUEST['id']) ? filter_var($_REQUEST['id'], FILTER_SANITIZE_NUMBER_INT) : 0));
            break;
        
        case 'applications':
                $lk->getUserApp((isset($_REQUEST['apptype']) && $_REQUEST['apptype'] != '' ? filter_var($_REQUEST['apptype'], FILTER_SANITIZE_NUMBER_INT) : null));
            break;
        
        case 'lockcourse':
                $lk->lockCourse((isset($_REQUEST['id']) ? filter_var($_REQUEST['id'], FILTER_SANITIZE_NUMBER_INT) : null));
            break;

        case 'inarch':
            $lk->inArch((isset($_REQUEST['course']) ? filter_var($_REQUEST['course'], FILTER_SANITIZE_NUMBER_INT) : null));
            break;

        case 'moderate':
                if ( !isset($_REQUEST['accept']) && !isset($_REQUEST['reject']) && !isset($_REQUEST['delete']) ){
                    $lk->getModeratorApp((isset($_REQUEST['apptype']) ? filter_var($_REQUEST['apptype'], FILTER_SANITIZE_NUMBER_INT) : 1),
                            null,
                            (isset($_REQUEST['course']) ? filter_var($_REQUEST['course'], FILTER_SANITIZE_NUMBER_INT) : null),
                        (isset($_REQUEST['page']) ?  filter_var($_REQUEST['page'], FILTER_SANITIZE_NUMBER_INT) : 1));
                    break;
                }
                if (isset($_REQUEST['accept'])) $state = APP_STATUS_ACCEPTED;
                if (isset($_REQUEST['reject'])) $state = APP_STATUS_REJECTED;
                if (isset($_REQUEST['delete'])) $state = APP_STATUS_DELETED;
                $lk->setAppState(
                                filter_var($_REQUEST['appid'], FILTER_SANITIZE_NUMBER_INT), 
                                $state, 
                                (isset($_REQUEST['course']) ? filter_var($_REQUEST['course'], FILTER_SANITIZE_NUMBER_INT) : 0),
                                true
                                );
                $lk->addRedirect($_SERVER['HTTP_REFERER'], 0);
            break;
            
        case 'check':
                if (isset($_REQUEST['userdata'])) $lk->userInfo( filter_var($_REQUEST['userdata'], FILTER_SANITIZE_NUMBER_INT) );
                if (isset($_REQUEST['work'])) $lk->workInfo( filter_var($_REQUEST['work'], FILTER_SANITIZE_NUMBER_INT) );
                if (isset($_REQUEST['passport'])) $lk->passInfo( filter_var($_REQUEST['passport'], FILTER_SANITIZE_NUMBER_INT) );
                if (isset($_REQUEST['diplom'])) $lk->diplomInfo( filter_var($_REQUEST['diplom'], FILTER_SANITIZE_NUMBER_INT) );
            break;
        
        case 'checkdata':
                if (isset($_REQUEST['user'])) $lk->checkData( filter_var($_REQUEST['user'], FILTER_SANITIZE_NUMBER_INT), 'users');
                if (isset($_REQUEST['work'])) $lk->checkData( filter_var($_REQUEST['work'], FILTER_SANITIZE_NUMBER_INT), 'work');
                if (isset($_REQUEST['pass'])) $lk->checkData( filter_var($_REQUEST['pass'], FILTER_SANITIZE_NUMBER_INT), 'pass');
                if (isset($_REQUEST['diplom'])) $lk->checkData( filter_var($_REQUEST['diplom'], FILTER_SANITIZE_NUMBER_INT), 'diplom');
            break;

        case 'group':
                $lk->getGroups(filter_var($_REQUEST['course'], FILTER_SANITIZE_NUMBER_INT),
                                (isset($_REQUEST['group']) ? filter_var($_REQUEST['group'], FILTER_SANITIZE_NUMBER_INT) : 0)
                            );
            break;

        case 'chugroup':
                $lk->changeUserGroup(filter_var($_REQUEST['user'], FILTER_SANITIZE_NUMBER_INT), filter_var($_REQUEST['group'], FILTER_SANITIZE_NUMBER_INT));
            break;

        case 'addnewgroup':
                $lk->createGroup(filter_var($_REQUEST['course'], FILTER_SANITIZE_NUMBER_INT), htmlspecialchars($_REQUEST['groupname']));
            break;
        
        case 'deletegroup':
                $lk->deleteGroup(filter_var($_REQUEST['id'], FILTER_SANITIZE_NUMBER_INT));
            break;
        
        case 'renamegroup':
                $lk->renameGroup(filter_var($_REQUEST['id'], FILTER_SANITIZE_NUMBER_INT), filter_var($_REQUEST['groupname'], FILTER_SANITIZE_STRING));
            break;
        
        case 'crossgroup':
                $json = $_REQUEST['student'];
                $student = json_decode($json, true);
                $lk->crossGroup(filter_var($_REQUEST['oldgroup'], FILTER_SANITIZE_NUMBER_INT), filter_var($_REQUEST['newgroup'], FILTER_SANITIZE_NUMBER_INT), $student);
            break;
        
        case 'printdoc':
                $dc = new \OnlineRecord\DocCreator($_REQUEST);
            break;

        case 'printpoz':
                $lk->printPOZ($_REQUEST);
            break;
        
        case 'printpoo':
                $lk->printPOO($_REQUEST);
            break;

        case 'printpoe':
            $lk->printPOE($_REQUEST);
            break;

        case 'settings':
                $lk->showSettings(filter_var($_REQUEST['type'], FILTER_SANITIZE_NUMBER_INT));
            break;
        
        case 'updateset':
                $lk->saveSettings($_REQUEST['set'], $_REQUEST['rules']);
            break;
        
        case 'getorderaddnumber':
                $lk->getOrderAddInfo($_REQUEST['group']);
            break;

        case 'getorderendnumber':
            $lk->getOrderEndInfo($_REQUEST['group']);
            break;

        case 'getorderexpnumber':
                $lk->getOrderExpInfo($_REQUEST['group']);
            break;
        
        case 'expulsestudent':
                $json = $_REQUEST['student'];
                $student = json_decode($json, true);
                $lk->expulseStudent($student);
            break;
        
        case 'docs':
                $lk->docMaster(isset($_REQUEST['page']) ? filter_var($_REQUEST['page'], FILTER_SANITIZE_NUMBER_INT) : 1);
            break;

        case 'pfro':
            $pfro = new \OnlineRecord\PFRO();
            $lk->addContent("PFRO");
            break;

        case 'userlist':
            if ($lk->isRule(\OnlineRecord\RULE_MODERATE)) {
                $lk->addContent($lk->getSearchUserForm());
                $lk->addContent('<script src="inc/tinysort.min.js"></script>');
                $lk->addJQCode(file_get_contents(dirname(__FILE__) . '/inc/searchUser.js'));

                $lk->addRtemplate('{%USERLIST%}', $lk->getUserList(array('type' => $_REQUEST['type'] ?? 'last',
                                                                         'mode' => $_REQUEST['mode'] ?? null,
                                                                         'text' => $_REQUEST['searchtext'] ?? null,
                                                                         'group' => $_REQUEST['group'] ?? null )));
            } else {
                $lk->addContent('Простите, вам сюда нельзя!');
            }
            break;

        case 'searchuser':
            $lk->addContent($lk->searchUser($_REQUEST['text'],'html', ($_REQUEST['group'] ?? null)));
            break;

        case 'help':
                $lk->setTitle('Справка по записи на курсы');
                $lk->addContent(file_get_contents(dirname(__FILE__) . '/inc/help.html'));
            break;
//FIXME!================================================================================== 
        case 'test':
                $pager = new Pager(15);
                $lk->addContent($pager->getPager());
            break;
//======================================================================================== 
        case 'show':
        default :
            /*$lk->addContent('<span style="color: red">Внимание! На данный момент наблюдаются проблемы с отправкой писем активации! ' .
                'Все учетные записи активируются в ручном режиме! Поэтому, не переживайте, если не получили письмо о регистрации, просто подождите несколько минут, перед тем, как войти со своими данными!</span>');*/
            $lk->catTree((isset($_REQUEST['cat']) ? $_REQUEST['cat'] : null));
            break;


            break;
    }
    
    echo $lk->getHtml();
    Error::pdump($lk, '$LK->');