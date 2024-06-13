<?php
declare(strict_types = 1);
namespace OnlineRecord;

use JasonGrimes\Paginator;

function getPaginator(int $count, int $itemsPerPage, int $current, string $urlPattern){
    $paginator = new Paginator($count, $itemsPerPage, $current, $urlPattern);
    if ($paginator->getNumPages() > 1) {
        $html = '<div class="input-group" style="width: 1px;">';
        if ($paginator->getPrevUrl()) {
            $html .= '<span class="input-group-btn">
                    <a href="<?php echo $paginator->getPrevUrl(); ?>" class="btn btn-default" type="button">&laquo; Prev</a>
                </span>';
        }

        $html .= '<select class="form-control paginator-select-page" style="width: auto; cursor: pointer; -webkit-appearance: none; -moz-appearance: none; appearance: none;">';
        foreach ($paginator->getPages() as $page) {
            if ($page['url']) {
                $html .= '<option value="' . $page['url'] . '"' . ($page['isCurrent'] ? ' selected' : '') . '>
                            Page ' . $page['num'] . '
                        </option>';
            } else {
                $html .= '<option disabled>' . $page['num'] . '</option>';
            }
                }
        $html .= '</select>';

        if ($paginator->getNextUrl()) {
            $html .= '<span class="input-group-btn">
                            <a href="' . $paginator->getNextUrl() . '" class="btn btn-default" type="button">Next &raquo;</a>
                          </span>';
        }
        $html .= '</div>';
    }
    return $html;
}

//========================================================================================================================================
/** Подготавливает массив для вывода в консоль FirePHP в виде таблицы. Транспонирует ключи и данные для вывода
 * @param array $a
 * @return array
 */
function arr4table($a) : array {
    return array_map(null, ...array(0 =>array_keys($a), 1 => array_values($a)));
}

//========================================================================================================================================
/** Функция, выводящая слова с разными окончаниями в зависимости от числа. Например '1 яблоко', '25 яблок', '432 яблока'.
 * 
 * @param integer $n - число к которому писать величины
 * @param array[3] $titles - массив величин. 
 *      0-й элемент - вижу одно|один (день).
 *      1-й элемент - вижу два (дня).
 *      2-й элемент - вижу пять (дней).

 *     echo number(631, array('день', 'дня', 'дней'));
 *     выведет 'день'.
 * @return type
 */
function number($n, $titles) {
  $cases = array(2, 0, 1, 1, 1, 2);
  return $titles[($n % 100 > 4 && $n % 100 < 20) ? 2 : $cases[min($n % 10, 5)]];
}

//========================================================================================================================================
/** Функция проверки корректности СНИЛС
	 * @param string $snils - номер СНИЛС в формате ХХХ-ХХХ-ХХХ ХХ
	 * @param string $error_message - сюда пишутся сообщения об ошибках
	 * @param mixed $error_code - код ошибки
	 * @return boolean - true если СНИЛС соответствует, false если ошибочный
	 **/
    function validateSnils($snils, &$error_message = null, $error_code = null) {
	$result = false;
	$snils = (string) $snils;
	if (!$snils) {
		$error_code = 1;
		$error_message = 'СНИЛС пуст';
	} elseif (!preg_match('/^\d{3}-\d{3}-\d{3}\s\d{2}$/', $snils)) {
		$error_code = 2;
		$error_message = 'СНИЛС может состоять только из цифр';
	} elseif (strlen($snils) !== 14) {
		$error_code = 3;
		$error_message = 'СНИЛС может состоять только из 11 цифр';
	} else {
		$sum = 0;
                
                $sum += (int) $snils[0]*9;
                $sum += (int) $snils[1]*8;
                $sum += (int) $snils[2]*7;
                $sum += (int) $snils[4]*6;
                $sum += (int) $snils[5]*5;
                $sum += (int) $snils[6]*4;
                $sum += (int) $snils[8]*3;
                $sum += (int) $snils[9]*2;
                $sum += (int) $snils[10]*1;

                $check_digit = 0;
		if ($sum < 100) {
			$check_digit = $sum;
		} elseif ($sum > 101) {
			$check_digit = $sum % 101;
			if ($check_digit === 100) {
				$check_digit = 0;
			}
		}
		if ($check_digit === (int) substr($snils, -2)) {
			$result = true;
		} else {
			$error_code = 4;
			$error_message = 'Неправильное контрольное число СНИЛС';
		}
	}
        return $result;
}

//=============================================================================================================================================
/**
 * Функция транслитерации из русского в латиницу
 * @param string $source - строка для транслитерации
 * @return string - транслитерированная строка
 */
    function RusToLat($source) {
            $rus = [
                'А', 'Б', 'В', 'Г', 'Д', 'Е', 'Ё', 'Ж', 'З', 'И', 'Й', 'К', 'Л', 'М', 'Н', 'О', 'П', 'Р', 'С', 'Т', 'У', 'Ф', 'Х', 'Ц', 'Ч', 'Ш', 'Щ', 'Ъ', 'Ы', 'Ь', 'Э', 'Ю', 'Я',
                'а', 'б', 'в', 'г', 'д', 'е', 'ё', 'ж', 'з', 'и', 'й', 'к', 'л', 'м', 'н', 'о', 'п', 'р', 'с', 'т', 'у', 'ф', 'х', 'ц', 'ч', 'ш', 'щ', 'ъ', 'ы', 'ь', 'э', 'ю', 'я'
            ];
            $lat = [
                'A', 'B', 'V', 'G', 'D', 'E', 'Yo', 'Zh', 'Z', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'R', 'S', 'T', 'U', 'F', 'H', 'C', 'Ch', 'Sh', 'Shh', '\'', 'Y', '\'', 'E', 'Yu', 'Ya',
                'a', 'b', 'v', 'g', 'd', 'e', 'yo', 'zh', 'z', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'r', 's', 't', 'u', 'f', 'h', 'c', 'ch', 'sh', 'shh', '\'', 'y', '\'', 'e', 'yu', 'ya'
            ];
            return str_replace($rus, $lat, $source);
    }
    
/** ========================================================================
     * Возвращает массив в виде строки, которую можно подставить в SQL запрос в IN()
     * Например [1,2,3] => '(1,2,3)'
     * @param array $arr
     * @return String
     */
    function arrIn(array $arr) {
        return '('. implode(',', $arr).')';
    }