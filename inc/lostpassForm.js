function validateSnils(snils, error) {
                        var result = false;
                        if (typeof snils === 'number') {
                                snils = snils.toString();
                        } else if (typeof snils !== 'string') {
                                snils = '';
                        }
                        if (!snils.length) {
                                error.code = 1;
                                error.message = 'СНИЛС пуст';
                        } else if (!/^\d{3}-\d{3}-\d{3}\s\d{2}$/.test(snils)) {
                                alert(/^\d{3}-\d{3}-\d{3}\s\d{2}$/.test('999-999-999 99'));
                                error.code = 2;
                                error.message = 'СНИЛС может состоять только из цифр';
                        } else if (snils.length !== 14) {
                                error.code = 3;
                                error.message = 'СНИЛС может состоять только из 11 цифр';
                        } else {
                                var sum = 0;
                                
				sum += parseInt(snils[0])*9;
                                sum += parseInt(snils[1])*8;
                                sum += parseInt(snils[2])*7;
                                sum += parseInt(snils[4])*6;
                                sum += parseInt(snils[5])*5;
                                sum += parseInt(snils[6])*4;
                                sum += parseInt(snils[8])*3;
                                sum += parseInt(snils[9])*2;
                                sum += parseInt(snils[10])*1;

            var checkDigit = 0;
                                if (sum < 100) {
                                        checkDigit = sum;
                                } else if (sum > 101) {
                                        checkDigit = parseInt(sum % 101);
                                        if (checkDigit === 100) {
                                                checkDigit = 0;
                                        }
                                }
                                if (checkDigit === parseInt(snils.slice(-2))) {
                                        result = true;
                                } else {
                                        error.code = 4;
                                        error.message = 'Неправильное контрольное число СНИЛС';
                                }
                        }
                        return result;
}//validateSnils(snils, error=====================================================================

function isValidEmailAddress(emailAddress) {
        var pattern = new RegExp(/^(("[\w-\s]+")|([\w-]+(?:\.[\w-]+)*)|("[\w-\s]+")([\w-]+(?:\.[\w-]+)*))(@((?:[\w-]+\.)*\w[\w-]{0,66})\.([a-z]{2,6}(?:\.[a-z]{2})?)$)|(@\[?((25[0-5]\.|2[0-4][0-9]\.|1[0-9]{2}\.|[0-9]{1,2}\.))((25[0-5]|2[0-4][0-9]|1[0-9]{2}|[0-9]{1,2})\.){2}(25[0-5]|2[0-4][0-9]|1[0-9]{2}|[0-9]{1,2})\]?$)/i);
        return pattern.test(emailAddress);
}//isValidEmailAddress(emailAddress)================================================================