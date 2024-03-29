<?php
/**
 * Форматирует цену, округляя до целого числа, отделяя разряды пробелом и добавляя знак рубля
 * @param float $num Цена
 * @return string Отформатированная цена
 */
function format_price($num) {
    $formated_num = ceil($num);

    if ($num >= 1000) {
        $formated_num = number_format($num, 0, "", " ");
    }

    $formated_num.= " ₽";

    return $formated_num;
}

/**
 * Рассчитывает временной интервал от текущего момента до переданной даты
 * @param string $date Дата в формате "ГГГГ-ММ-ДД"
 * @return array Время, остающееся до наступления указанной даты
 */
function count_time_diff($date) {
    $date_now = date_create("now");
    $date_future = date_create($date);
    $hours_before_date = 0;
    $minutes_before_date = 0;
    $seconds_before_date = 0;

    if ($date_future > $date_now) {
        $diff = date_diff($date_now, $date_future);
        $days_diff = date_interval_format($diff, "%a");
        $hours_diff = date_interval_format($diff, "%h");
        $minutes_before_date = date_interval_format($diff, "%i");
        $seconds_before_date = date_interval_format($diff, "%s");
        $hours_before_date = $days_diff * 24 + $hours_diff;
    }

    return [$hours_before_date, $minutes_before_date, $seconds_before_date];
}

/**
 * Возвращает дополнительное название класса, если до истечения лота осталось меньше часа, указанный пользователь победил в торгах на лот, или торги окончены
 * @param array $data Массив с данными лота
 * @param string $field Ключ поля с датой в массиве $data
 * @return string Возвращаемый класс
 */
function return_timer_class($data, $field) {
    if (isset($data["user_id"]) && isset($data["winner_id"]) && ($data["user_id"] === $data["winner_id"])) {
        return "  timer--win";
    }

    if (array_sum(count_time_diff($data[$field])) === 0) {
        return " timer--end";
    }

    [$hours_left] = count_time_diff($data[$field]);

    if ($hours_left < 1) {
        return " timer--finishing";
    }

    return "";
}

/**
 * Возвращает данные о том, сколько времени осталось до истечения лота. Если торги на лот окончены или ставка выиграла, возвращает соответствующую информацию
 * @param array $data Массив с данными лота
 * @param string $field Ключ поля с датой в массиве $data
 * @param bool $should_count_seconds Должен ли таймер считать секунды. Необязательный параметр, по умолчанию равен false
 * @return string Возвращаемые данные
 */
function print_timer($data, $field, $should_count_seconds = false) {    
    if (isset($data["user_id"]) && isset($data["winner_id"]) && ($data["user_id"] === $data["winner_id"])) {
        return "Ставка выиграла";
    }

    $time = count_time_diff($data[$field]);

    if (array_sum($time) === 0) {
        return "Торги окончены";
    }

    if (!$should_count_seconds) {
        array_pop($time);
    }

    foreach ($time as & $num) {
        $num = str_pad($num, 2, "0", STR_PAD_LEFT);
    }

    return implode(":", $time);
}

/**
 * Возвращает массив всех данных из таблицы category
 * @param mysqli $con Подключение к ДБ
 * @return array Массив данных из таблицы category
 */
function get_categories($con) {
    $data = [];
    $sql = "SELECT * FROM category";
    $result = mysqli_query($con, $sql);

    if ($result) {
        $data = mysqli_fetch_all($result, MYSQLI_ASSOC);
    }

    return $data;
}

/**
 * Возвращает категорию из таблицы category в соответствии с переданным id
 * @param mysqli $con Подключение к ДБ
 * @param int $id Id категории
 * @return array Массив данных из таблицы category
 */
function get_category_by_id($con, $id) {
    $data = [];

    $sql = "SELECT * FROM category
            WHERE id = {$id}";

    $result = mysqli_query($con, $sql);

    if ($result) {
        $data = mysqli_fetch_assoc($result);
    }

    return $data;
}

/**
 * Возвращает результат запроса к БД с данными таблицы лотов, категорией лота и его текущей ценой
 * @param mysqli $con Подключение к ДБ
 * @param string $condition Дополнительное условие запроса к БД, по умолчанию равно пустой строке
 * @return mysqli_result Результат запроса к БД
 */
function prepare_lots_query($con, $condition = "") {
    $sql = "SELECT 
                  lots.*,
                  IFNULL(lots.current_price, lots.start_price) as price,
                  u.name AS last_bid_user_name,
                  u.email AS last_bid_user_email   
            FROM (
                    SELECT l.*,
                           c.name as category,
                           (   
                               SELECT value
                               FROM bid as b
                               WHERE 
                                    b.lot_id = l.id
                               ORDER BY 
                                    b.value DESC
                               LIMIT 1
                           ) as current_price,
                           (
                                SELECT user_id
                                FROM bid as b
                                WHERE 
                                    b.lot_id = l.id
                                ORDER BY 
                                    b.value DESC
                                LIMIT 1
                            ) as last_bid_user_id,
                           (   
                               SELECT COUNT(*)
                               FROM bid as b
                               WHERE 
                                    b.lot_id = l.id
                           ) as bids_num
                    FROM lot as l
                    JOIN category as c
                    ON 
                        l.category_id = c.id 
                    {$condition}
                ) as lots
                LEFT JOIN user as u
                ON u.id = lots.last_bid_user_id";

    $result = mysqli_query($con, $sql);

    return $result;
}

/**
 * Возвращает массив с данными открытых лотов из таблицы lot, название категории, к которой принадлежит лот, и его текущую цену с учётом ставок
 * @param mysqli $con Подключение к ДБ
 * @return array Массив данных из таблицы lot
 */
function get_active_lots($con) {
    $data = [];

    $condition = "WHERE 
                    l.date_expire > NOW() 
                  ORDER BY 
                    l.date_create DESC";

    $result = prepare_lots_query($con, $condition);

    if ($result) {
        $data = mysqli_fetch_all($result, MYSQLI_ASSOC);
    }

    return $data;
}

/**
 * Возвращает массив с данными истекших лотов, у которых нет победителя, из таблицы lot, название категории, к которой принадлежит лот, и его текущую цену с учётом ставок
 * @param mysqli $con Подключение к ДБ
 * @return array Массив данных из таблицы lot
 */
function get_lots_without_winner($con) {
    $data = [];

    $condition = "WHERE 
                    l.date_expire <= NOW() 
                  AND 
                    l.winner_id IS NULL
                  HAVING
                    last_bid_user_id IS NOT NULL
                  ";

    $result = prepare_lots_query($con, $condition);

    if ($result) {
        $data = mysqli_fetch_all($result, MYSQLI_ASSOC);
    }

    return $data;
}

/**
 * Возвращает массив с данными активных лотов из таблицы lot, соответствующих переданной категории, и их текущую цену с учётом ставок
 * @param mysqli $con Подключение к ДБ
 * @param string $category Название категории
 * @param int $page_items Количество лотов, выводимых на странице. Необязательный параметр
 * @param int $cur_page Номер текущей страницы. Необязательный параметр
 * @return array Массив данных из таблицы lot
 */
function get_active_lots_by_category($con, $category, $cur_page = null, $page_items = null) {
    $data = [];

    $condition = "WHERE 
                    {$category} = c.id
                  AND 
                    l.date_expire > NOW() 
                  ORDER BY 
                    l.date_create DESC";

    if (isset($page_items) && isset($cur_page)) {
        $offset = ($cur_page - 1) * $page_items;
        $condition.= " LIMIT {$page_items} OFFSET {$offset}";
    }

    $result = prepare_lots_query($con, $condition);

    if ($result) {
        $data = mysqli_fetch_all($result, MYSQLI_ASSOC);
    }

    return $data;
}

/**
 * Возвращает числовое значение переменной
 * @param string $paramName Название параметра, получаемого из массива параметров
 * @param array $params Массив параметров
 * @return int Числовое значение переменной
 */
function return_int_from_query($paramName, $params) {
    return intval(get_param_from_query($paramName, $params));
}

/**
 * Возвращает значение указанного параметра из массива параметров, или пустую строку, если он не задан
 * @param string $paramName Название параметра, получаемого из массива параметров
 * @param array $params Массив параметров
 * @return string Значение параметра
 */
function get_param_from_query($paramName, $params) {
    return $params[$paramName]??"";
}

/**
 * Возвращает массив с данными лота, соответствующего переданному id, название категории, к которой принадлежит лот, и его текущую цену с учётом ставок
 * @param mysqli $con Подключение к ДБ
 * @param string $id Идентификатор лота
 * @return array Массив данных из таблицы lot
 */
function get_lot_by_id($con, $id) {
    $id = mysqli_real_escape_string($con, $id);
    $data = [];
    $condition = "WHERE l.id = {$id}";
    $result = prepare_lots_query($con, $condition);

    if ($result) {
        $data = mysqli_fetch_assoc($result);
    }

    return $data;
}

/**
 * Возвращает значения прежде заполненных полей из массива POST
 * @param string $name Имя поля
 * @return string Значение поля
 */
function get_post_val($name) {
    return $_POST[$name] ?? "";
}

/**
 * Возвращает массив данных из массива $_POST, отфильтрованный по нужным полям"
 *
 * @param array $data Данные из массива $_POST
 * @param array $fields Названия нужных полей *
 * @return array Массив данных
 */
function filter_post_data($data, $fields) {
    return array_intersect_key($data, array_flip($fields));
}

/**
 * Возвращает данные пользователя с указанным email из таблицы user
 * @param mysqli $con Подключение к ДБ
 * @param string $email Email пользователя
 * @return array Данные пользователя
 */
function get_user_from_db($con, $email) {
    $email = mysqli_real_escape_string($con, $email);
    $sql = "SELECT * FROM user WHERE email = '$email'";
    $result = mysqli_query($con, $sql);
    $user = mysqli_fetch_assoc($result);

    return $user;
}

/**
 * Возвращает данные о ставках пользователя с указанным id
 * @param mysqli $con Подключение к ДБ
 * @param string $user_id Id пользователя
 * @return array Данные о ставках
 */
function get_user_bids($con, $user_id) {
    $user_id = mysqli_real_escape_string($con, $user_id);
    $data = [];

    $sql = "SELECT 
                b.user_id, 
                b.value as bid_value, 
                b.date_create as bid_date_create,
                l.id as lot_id, 
                l.name as lot_name, 
                l.image_url, 
                l.date_expire as lot_date_expire, 
                l.winner_id, 
                u.contacts as seller_contacts, 
                c.name as category_name 
            FROM bid as b 
            JOIN lot as l 
            ON b.lot_id = l.id 
            JOIN user as u 
            ON l.seller_id = u.id
            JOIN category as c 
            ON l.category_id = c.id 
            WHERE 
                b.user_id = {$user_id}
            ORDER BY 
                bid_date_create DESC";

    $result = mysqli_query($con, $sql);

    if ($result) {
        $data = mysqli_fetch_all($result, MYSQLI_ASSOC);
    }

    return $data;
}

/**
 * Возвращает данные о ставках на лот с указанным id
 * @param mysqli $con Подключение к ДБ
 * @param string $lot_id Id лота
 * @return array Данные о ставках
 */
function get_lot_bids($con, $lot_id) {
    $data = [];

    $sql = "SELECT b.user_id, 
               b.value as bid_value, 
               b.date_create as bid_date_create,
               l.id as lot_id, 
               l.name as lot_name, 
               l.image_url, 
               l.date_expire as lot_date_expire, 
               l.winner_id, 
               u.name as candidat_name,
               u.email as candidat_email
        FROM bid as b 
        JOIN lot as l 
        ON b.lot_id = l.id 
        JOIN user as u 
        ON b.user_id = u.id
        WHERE l.id = '$lot_id'
        ORDER BY bid_value DESC";

    $result = mysqli_query($con, $sql);

    if ($result) {
        $data = mysqli_fetch_all($result, MYSQLI_ASSOC);
    }

    return $data;
}

/**
 * Возвращает строку ошибки, если пользователь при регистрации вводит уже существующий в БД email, или null
 * @param mysqli $con Подключение к ДБ
 * @param string $email Email пользователя
 * @return string | null Текст ошибки или null
 */
function check_double_email($con, $email) {
    $user = get_user_from_db($con, $email);

    if (!empty($user)) {
        return "Пользователь с этим email уже зарегистрирован";
    }

    return null;
}

/**
 * Возвращает строку ошибки, если пользователь с указанным email не найден в БД, или null
 * @param mysqli $con Подключение к ДБ
 * @param string $email Email пользователя
 * @return string | null Текст ошибки или null
 */
function check_existing_email($con, $email) {
    $user = get_user_from_db($con, $email);

    if (empty($user)) {
        return "Такой пользователь не найден";
    }

    return null;
}

/**
 * Возвращает строку ошибки, если введенный пароль не совпадает с паролем, указанным при регистрации пользователя с данным email
 * @param mysqli $con Подключение к ДБ
 * @param array $data Данные, отфильтрованные из массива $_POST
 * @return string | null Текст ошибки или null
 */
function verify_password($con, $data) {
    $user = get_user_from_db($con, $data["email"]);

    if (!empty($user)) {
        if (password_verify($data["password"], $user["password"])) {
            return null;
        }
        return "Пароль неверен";
    }

    return "Ошибка подключения к БД";
}

/**
 * Возвращает массив ошибок, полученных после валидации полей формы
 * @param array $data Данные, отфильтрованные из массива $_POST
 * @param array $validators Массив с правилами валидации
 * @param array $additional_data Дополнительные данные, по умолчанию пустой массив
 * @return array Массив ошибок
 */
function validate_form($data, $validators, $additional_data = array()) {
    $errors = [];

    foreach ($data as $key => $value) {

        if (is_callable($validators[$key])) {
            $rule = $validators[$key];

            if (count($additional_data)) {
                $errors[$key] = call_user_func_array($rule, array($data, $additional_data));
            } else {
                $errors[$key] = call_user_func($rule, $data);
            }
        }
    };

    $errors = array_filter($errors);

    return $errors;
}

/**
 * Возвращает массив ошибок, полученных после валидации полей формы "Регистрация нового пользователя"
 * @param mysqli $con Подключение к ДБ
 * @param array $data Данные, отфильтрованные из массива $_POST
 * @param array $validators Массив с правилами валидации
 * @return array Массив ошибок
 */
function validate_registration_form($con, $data, $validators) {
    $errors = validate_form($data, $validators);

    if (empty($errors)) {
        $errors["email"] = check_double_email($con, $data["email"]);
    }

    $errors = array_filter($errors);

    return $errors;
}

/**
 * Возвращает массив ошибок, полученных после валидации формы "Авторизация"
 * @param mysqli $con Подключение к ДБ
 * @param array $data Данные, отфильтрованные из массива $_POST
 * @param array $validators Массив с правилами валидации
 * @return array Массив ошибок
 */
function validate_login_form($con, $data, $validators) {
    $errors = validate_form($data, $validators);

    if (empty($errors)) {
        $errors["email"] = check_existing_email($con, $data["email"]);
        $errors = array_filter($errors);

        if (empty($errors)) {
            $errors["password"] = verify_password($con, $data);
        }
    }
    $errors = array_filter($errors);

    return $errors;
}

/**
 * Возвращает массив ошибок, полученных после валидации полей формы "Добавить новый лот"
 * @param array $data Данные, отфильтрованные из массива $_POST
 * @param array $validators Массив с правилами валидации
 * @return array Массив ошибок
 */
function validate_lot($data, $validators) {
    $errors = validate_form($data, $validators);
    $errors["lot-img"] = validate_image($_FILES["lot-img"]);
    $errors = array_filter($errors);

    return $errors;
}

/**
 * Возвращает текстовую строку, которая выводится при ошибки валидации поля "Категория", или null, если ошибки нет
 * @param array $data Данные, отфильтрованные из массива $_POST
 * @param string $field Имя поля в массиве $_POST
 * @param array $cats_ids Массив id существующих категорий
 * @return string | null Текст ошибки или null
 */
function validate_category($data, $field, $cats_ids) {
    $id = $data[$field];

    if (!in_array($id, $cats_ids)) {
        return "Укажите существующую категорию";
    }

    return null;
}

/**
 * Возвращает текст ошибки, если обязательное поле формы не заполнено, или null, если ошибки нет
 * @param array $data Данные, отфильтрованные из массива $_POST
 * @param string $field Имя поля в массиве $_POST
 * @return string | null Текст ошибки или null
 */
function validate_filled($data, $field) {
    if (empty($data[$field])) {
        return "Это поле должно быть заполнено";
    }

    return null;
}

/**
 * Возвращает текст ошибки, если email, указанный пользователем, не валиден, или null, если ошибки нет
 * @param array $data Данные, отфильтрованные из массива $_POST
 * @param string $field Имя поля в массиве $_POST
 * @return string | null Текст ошибки или null
 */
function validate_email($data, $field) {
    if (!filter_var($data[$field], FILTER_VALIDATE_EMAIL)) {
        return "Ваш Email не корректен";
    }

    return null;
}

/**
 * Возвращает текст ошибки, если длина переданного поля превышает указанную максимальную длину
 * @param array $data Данные, отфильтрованные из массива $_POST
 * @param string $field Имя поля в массиве $_POST
 * @param int $max Максимальная длина поля
 * @return string | null Текст ошибки или null
 */
function is_correct_length($data, $field, $max) {
    $len = strlen($data[$field]);

    if ($len > $max) {
        return "Значение должно быть менее $max символов"; 
    }

    return null;
}

/**
 * Возвращает текст ошибки, если в поле формы указано не целое положительное число, или null, если ошибки нет
 * @param array $data Данные, отфильтрованные из массива $_POST
 * @param string $field Имя поля в массиве $_POST
 * @return string | null Текст ошибки или null
 */
function is_num_positive_int($data, $field) {
    if (!ctype_digit($data[$field]) || $data[$field] <= 0) {
        return "Введите целое положительное число";
    }

    return null;
}

/**
 * Возвращает текст ошибки, если указанная пользователем ставка меньше суммы текущей цены лота и минальной ставки, или null, если ошибки нет
 * @param array $data Данные, отфильтрованные из массива $_POST
 * @param string $field Имя поля в массиве $_POST
 * @param array $lot Данные лота, полученные из таблицы lot
 * @return string | null Текст ошибки или null
 */
function validate_bid($data, $field, $lot) {
    if ($data[$field] < ($lot["price"] + $lot["bid_step"])) {
        return "Введите сумму, превышающую текущую цену на размер минимальной ставки";
    }

    return null;
}

/**
 * Возвращает текст ошибки, если дата, указанная в поле формы, не соответствует формату "ГГГГ-ММ-ДД", или дата не больше текущей на день, или null, если ошибки нет
 * @param array $data Данные, отфильтрованные из массива $_POST
 * @param string $field Имя поля в массиве $_POST
 * @return string | null Текст ошибки или null
 */
function validate_date($data, $field) {
    if (is_date_valid($data[$field])) {
        $ts_date = strtotime($data[$field]);
        $tomorrow = strtotime("tomorrow midnight");

        if ($ts_date < $tomorrow) {
            return "Указанная дата должна быть больше текущей хотя бы на один день";
        }

        return null;
    }

    return "Введите дату в формате ГГГГ-ММ-ДД";
}

/**
 * Возвращает текст ошибки, если изображение не загружено или не соответствует необходимому формату
 * @param array $file Данные изображениея
 * @return string | null Текст ошибки или null
 */
function validate_image($image) {
    if (!empty($image["name"])) {
        return validate_image_format($image);
    }

    return "Загруузите изображение лота";
}

/**
 * Возвращает текст ошибки, если формат картинки не соответствует jpg или png, или null, если ошибки нет
 * @param array $file Данные файла из массива $_FILES
 * @return string | null Текст ошибки или null
 */
function validate_image_format($file) {
    $file_name = $file["tmp_name"];
    $file_type = mime_content_type($file_name);

    if ($file_type !== "image/jpeg" && $file_type !== "image/png") {
        return "Загрузите картинку в формате png, jpg или jpeg";
    }

    return null;
}

/**
 * Возвращает новую ссылку на файл после перемещения его из временной папки в папку uploads
 * @param array $file Данные файла из массива $_FILES
 * @return string Текст ошибки
 */
function move_file($file) {
    $info = new SplFileInfo($file["name"]);
    $extension = $info->getExtension();
    $file_name = md5(microtime() . rand(0, 9999)) . '.' . $extension;
    $file_path = __DIR__ . '/uploads/';
    $file_url = '/uploads/' . $file_name;
    move_uploaded_file($file["tmp_name"], $file_path . $file_name);

    return $file_url;
}

/**
 * Получает данные из таблицы ДБ, соответствующие переданному выражению и защищённые от sql-инъекций
 * @param mysqli $con Подключение к ДБ
 * @param string $sql Строка запроса к ДБ
 * @param array $data Массив значений, которые передаются в подготовленное выражение, по умолчанию пустой
 * @return string | bool id добавленной строки или false
 */
function db_get_data($con, $sql, $data = []) {
    $stmt = db_get_prepare_stmt($con, $sql, $data);
    $result = mysqli_stmt_get_result($stmt);

    if ($result) {
        $result = mysqli_fetch_all($result, MYSQLI_ASSOC);
    }

    return $result;
}

/**
 * Записывает данные в таблицу ДБ и возвращает id добавленной строки
 * @param mysqli $con Подключение к ДБ
 * @param string $sql Строка запроса к ДБ
 * @param array $data Массив значений, которые передаются в подготовленное выражение, по умолчанию пустой
 * @return string | bool id добавленной строки или false
 */
function db_insert_data($con, $sql, $data = []) {
    $stmt = db_get_prepare_stmt($con, $sql, $data);
    $result = mysqli_stmt_execute($stmt);

    if ($result) {
        $result = mysqli_insert_id($con);
    }

    return $result;
}

/**
 * Записывает данные лота из массива $_POST в таблицу lot и возвращает id этого лота
 * @param mysqli $con Подключение к ДБ
 * @param array $data Данные, отфильтрованные из массива $_POST
 * @param string $user_id Id пользователя из сессии
 * @return string | bool id лота или false
 */
function insert_lot($con, $data, $user_id) {
    $name = $data["lot-name"];
    $description = $data["message"];
    $image_url = move_file($_FILES["lot-img"]);
    $start_price = $data["lot-rate"];
    $date_expire = $data["lot-date"];
    $bid_step = $data["lot-step"];
    $category_id = $data["category"];

    $sql = "INSERT INTO lot (name, description, image_url, start_price, date_expire, bid_step, category_id, seller_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

    return db_insert_data($con, $sql, [$name, $description, $image_url, $start_price, $date_expire, $bid_step, $category_id, $user_id]);
}

/**
 * Записывает данные пользователя из массива $_POST в таблицу user и возвращает id этого пользователя
 * @param mysqli $con Подключение к ДБ
 * @param array $data Данные, отфильтрованные из массива $_POST
 * @return string | bool id пользователя или false
 */
function insert_new_user($con, $data) {
    $email = $data["email"];
    $password = password_hash($data["password"], PASSWORD_DEFAULT);
    $name = $data["name"];
    $contacts = $data["message"];

    $sql = "INSERT INTO user (email, name, password, contacts) VALUES (?, ?, ?, ?)";

    return db_insert_data($con, $sql, [$email, $name, $password, $contacts]);
}

/**
 * Записывает айди пользователя, выигравшего лот, в кололнку winner_id таблицы lot
 * @param mysqli $con Подключение к ДБ
 * @param string $user_id Айди пользователя
 * @param string $lot_id Айди лота
 * @return string | bool id пользователя или false
 */
function update_lot_winner($con, $winner_id, $lot_id) {
    $data = mysqli_real_escape_string($con, $winner_id);

    $sql = "UPDATE lot SET winner_id = '{$data}'
            WHERE
                lot.id = {$lot_id}";

    return mysqli_query($con, $sql);
}

/**
 * Записывает данные пользователя из массива $_POST в таблицу user и возвращает id этого пользователя
 * @param mysqli $con Подключение к ДБ
 * @param array $data Данные, отфильтрованные из массива $_POST
 * @param string $user_id Id пользователя из сессии
 * @param string $lot_id Id лота
 * @return string id пользователя
 */
function insert_new_bid($con, $data, $user_id, $lot_id) {
    $bid_value = $data["cost"];

    $sql = "INSERT INTO bid (value, user_id, lot_id) VALUES (?, ?, ?)";

    return db_insert_data($con, $sql, [$bid_value, $user_id, $lot_id]);
}

/**
 * Возвращает дополнительное название класса для ставки
 * @param $data array Массив с данными лота и ставки
 * @param string $field Ключ поля с датой в массиве $data
 * @return string Пустая строка или дополнительное название класса
 */
function return_bid_class($data, $field) {
    if ($data["user_id"] === $data["winner_id"]) {
        return " rates__item--win";
    }

    $is_time_before_expire = (time() < strtotime($data[$field]));

    if ($is_time_before_expire) {
        return "";
    }

    return " rates__item--end";
}

/**
 * Возвращает отформатированную строку, указывающую, сколько времени прошло от момента переданной даты
 * @param $date string Cтрока с датой на английском языке
 * @return string Отформатированная строка
 */
function return_formated_time($date) {
    $ts_date = strtotime($date);
    $today = strtotime("today midnight");
    $yesterday = strtotime("yesterday midnight");

    if ($ts_date >= $today) {
        $time_left = time() - $ts_date;
        $hours_left = floor($time_left / 3600);
        $minutes_left = floor(($time_left % 3600) / 60);
        $hours_string = "$hours_left " . get_noun_plural_form($hours_left, "час", "часа", "часов");
        $minutes_string = "$minutes_left " . get_noun_plural_form($minutes_left, "минута", "минуты", "минут");

        if ($hours_left < 1) {
            return $minutes_string . " назад";
        }

        return $hours_string . " " . $minutes_string . " назад";
    }

    if ($ts_date >= $yesterday) {
        $time = date("H:i", $ts_date);

        return "Вчера, в $time";
    }

    return date("d.m.Y в H:i", $ts_date);
}

/**
 * Находит в БД активные лоты, соответствубющие переданному запросу
 * @param mysqli $con Подключение к ДБ
 * @param string $search Данные из массива $_GET
 * @param int $cur_page Номер текущей страницы. Необязательный параметр
 * @param int $page_items Количество лотов, выводимых на странице. Необязательный параметр
 * @return array Найденные лоты
 */
function search_active_lots($con, $search, $cur_page = null, $page_items = null) {
    $data = [];
    $string = mysqli_real_escape_string($con, $search);

    $condition = "WHERE 
                    MATCH(l.name, l.description) AGAINST('{$string}')
                  AND 
                    l.date_expire > NOW() 
                  ORDER BY 
                    l.date_create DESC";

    if (isset($page_items) && isset($cur_page)) {
        $offset = ($cur_page - 1) * $page_items;
        $condition.= " LIMIT {$page_items} OFFSET {$offset}";
    }

    $result = prepare_lots_query($con, $condition);

    if ($result) {
        return mysqli_fetch_all($result, MYSQLI_ASSOC);
    }

    return $data;
}

/**
 * Проверяет, истёк ли лот с переданным id
 * @param int $lot Id лота
 * @return bool Истёк ли лот
 */
function is_lot_expired($lot) {
    return strtotime("now") > strtotime($lot["date_expire"]);
}

/**
 * Возвращает данные для построения блока пагинации: количество страниц, массив с номерами всех страниц, сколько лотов из запроса должны быть проигнорированы
 * @param int $page Текущая страница
 * @param array $lots Массив с данными лотов
 * @param int $page_items Количество лотов, которые должны отображаться на странице
 * @return array Массив данных
 */
function get_pages_info($page, $lots, $page_items) {
    $result = [];
    $result["pages"] = [0];
    $items_count = count($lots);
    $pages_count = (int)ceil($items_count / $page_items);

    if ($items_count > 0) {
        $result["pages"] = range(1, $pages_count);
    }

    $result["pages_count"] = $pages_count;

    return $result;
}