<?php

namespace SZonov\Rudate;

/**
 * Набор полезных функций для работы с русскими датами
 * Используется кодировка UTF-8
 *
 * Class Rudate
 * @package SZonov\Rudate
 */
class Rudate
{
    /**
     * @var Rudate
     */
    protected static $_instance;

    /**
     * Возможность использовать как синглтон
     *
     * @return Rudate
     */
    public static function instance()
    {
        if (static::$_instance === null)
            static::$_instance = new static();
        return static::$_instance;
    }

    /**
     * Дополнение к стандартной strftime функции, позволяет использовать конструкции вида
     * {Месяц} {месяц} {Месяца} {месяца}
     * Заменяется на полное склоненное русское название месяца
     *
     * Примеры:
     * setlocale(LC_TIME, 'ru_RU')
     *
     *   $this->strftime('%e {месяца} %Y', 946699810)  // '1 января 2000'
     *   $this->strftime('%e {Месяца} %Y', 946699810)  // '1 Января 2000'
     *   $this->strftime('{Месяц} %Y', 946699810)      // 'Январь 2000'
     *   $this->strftime('{месяц} %Y', 946699810)      // 'январь 2000'
     *
     * setlocale(LC_TIME, 'en_US')
     *
     *   $this->strftime('%e {месяца} %Y', 946699810)  // '1 January 2000'
     *   $this->strftime('%e {Месяца} %Y', 946699810)  // '1 January 2000'
     *   $this->strftime('{Месяц} %Y', 946699810)      // 'January 2000'
     *   $this->strftime('{месяц} %Y', 946699810)      // 'January 2000'
     *
     * @param string $format
     * @param null|int $time
     * @return string
     */
    public function strftime($format, $time = null)
    {
        if ($time === null)
            $time = time();

        // 946699810 = mktime от даты '2000-01-01 10:10:10'
        // если оригинальная strftime говорит, что короткое название месяца для этого времени = 'янв',
        // то мы полагаем, что сейчас используется русский язык -> будем склонять названия месяцев
        // если не 'янв' - используем стандартную '%B' вместо наших конструкций
        // !! использование setlocale(LC_TIME, 0) - выдает на разных ОС, разный формат,
        // с 'янв' все одинаково на разных ОС-ях
        if (strftime('%b', 946699810) === 'янв')
        {
            $month_list = array('','января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля',
                'августа', 'сентября', 'октября', 'ноября', 'декабря');

            $month_list_1 = array('','январь', 'февраль', 'март', 'апрель', 'май', 'июнь', 'июль',
                'август', 'сентябрь', 'октябрь', 'ноябрь', 'декабрь');

            $month = $month_list[date('n', $time)];
            $month_1 = $month_list_1[date('n', $time)];
        }
        else
        {
            $month = '%B';
            $month_1 = '%B';
        }

        $format = preg_replace_callback('/{([Мм])есяц(а?)}/u', function ($matches) use ($month, $month_1) {
            $name = ($matches[2] == 'а') ? $month : $month_1;
            return ($matches[1] === 'М') ? mb_strtoupper(mb_substr($name, 0,1)) . mb_substr($name, 1) : $name;
        }, $format);

        return trim(strftime($format, $time));
    }

    /**
     * Получение даты в формате 'YYYY-MM-DD' из строки, содержащей русское написание даты
     * Валидные примеры:
     *   31.12.2011г.
     *   31.12.2011 г.
     *   «23»   января  2011
     *   « 23 » января  2011
     *   "23"   января  2011
     *    23    янв.    2011
     *
     * @param $string
     * @return false|string
     */
    public function parse($string)
    {
        $regexp = '/(\d{2})\.(\d{2})\.(\d{4})/iu';
        if (preg_match($regexp, $string, $r))
            return (checkdate($r[2], $r[1], $r[3])) ? sprintf('%04d-%02d-%02d', $r[3], $r[2], $r[1]) : false;

        $regexp = '/[«"]?\s*(\d+)\s*[»"]?\s*([^\s]+)\s*(\d{4})/iu';
        if (!preg_match($regexp, $string, $r))
            return false;

        $day = $r[1];
        $year = $r[3];
        $list = array('я','ф','мар','ап', 'м', 'июн', 'июл', 'а', 'с', 'о', 'н', 'д');

        if (!preg_match('/^('.join('|', $list).')/iu', trim($r[2]), $r))
            return false;

        $month = array_search(mb_strtolower($r[1]), $list) + 1;

        if (!checkdate($month, $day, $year))
            return false;

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    /**
     * Получение строки характеризующей период между двумя датами,
     * Даты задаются в формате 'YYYY-MM-DD'
     *
     * Примеры:
     *  1.    2016-01-01 | 2016-01-01      // 1 января 2016
     *  2.1   2016-01-01 | 2016-01-31      // январь 2016
     *  2.2   2016-01-01 | 2016-01-10      // 1 - 10 января 2016
     *  3.1   2016-01-01 | 2016-12-31      // 2016 год
     *  3.2   2016-02-01 | 2016-05-05      // 1 февраля - 5 мая 2016
     *  4     2016-01-02 | 2017-12-31      // 2 января 2016 - 31 декабря 2017
     *
     * @param string $start_date
     * @param string $end_date
     * @return string
     */
    public function period($start_date, $end_date)
    {
        $start_time  = strtotime($start_date);
        $end_time    = strtotime($end_date);

        // 1. дата одна и та же
        if (date('Ymd', $start_time) == date('Ymd', $end_time))
            return $this->strftime('%e {месяца} %Y', $start_time);

        // 2. обе даты в одном и том же месяце
        if (date('Ym', $start_time) == date('Ym', $end_time))
        {
            // 2.1 Начало - 1-ое число, Конец - последний день месяца
            $last_month_day = date('t', $end_time);

            if (date('d', $start_time) == '01' && date('d', $end_time) == $last_month_day)
                return $this->strftime('{месяц} %Y', $start_time);

            // 2.2 Произвольные числа одного месяца
            return $this->strftime('%e', $start_time) . ' - ' . $this->strftime('%e {месяца} %Y', $end_time);
        }

        // 3. обе даты в одном и том же году
        if (date('Y', $start_time) == date('Y', $end_time))
        {
            // 3.1 Начало 1-е января, конец 31 декабря
            if (date('md', $start_time) == '0101' && date('md', $end_time) == '1231')
                return date('Y', $start_time) . ' год';

            // 3.2 Произвольные даты одного года
            return $this->strftime('%e {месяца}', $start_time) . ' - ' . $this->strftime('%e {месяца} %Y', $end_time);
        }
        // 4. даты из разных годов, возвращаем полный промежуток
        return $this->strftime('%e {месяца} %Y', $start_time) . ' - ' . $this->strftime('%e {месяца} %Y', $end_time);
    }
}