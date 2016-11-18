<?php

namespace Carbon;

use SZonov\Rudate\Rudate;

/**
 * Подмена функции внутри namespace Carbon => Carbon будет использовать нашу функцию, а не глобальную strftime
 *
 * Добавление этой функции дает возможность использовать в $carbon->formatLocalized('%e {месяца} %Y')
 *
 * @param string $format
 * @param null|int $time
 * @return string
 */
function strftime($format, $time = null)
{
    return Rudate::instance()->strftime($format, $time);
}