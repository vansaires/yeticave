<?php
/**
 * Форматирует цену, округляя до целого числа, отделяя разряды пробелом и добавляя знак рубля
 * @param float $num Цена
 * @return string Отформатированная цена
 */
function format_price($num)
{
    $formated_num = ceil($num);

    if ($num >= 1000) {
        $formated_num = number_format($num, 0, "", " ");
    }

    $formated_num .= " ₽";
    return $formated_num;
}
/**
 * Рассчитывает временной интервал от текущего момента до переданной даты
 * @param string $date Дата в формате "ГГГГ-ММ-ДД"
 * @return array Часы и минуты, остающиеся до наступления указанной даты
 */
function count_time_diff($date)
{
    $date_now = date_create("now");
    $date_future = date_create($date);
    $hours_before_date = 0;
    $minutes_before_date = 0;

    if ($date_future > $date_now) {
        $diff = date_diff($date_now, $date_future);
        $days_diff = date_interval_format($diff, "%a");
        $hours_diff = date_interval_format($diff, "%h");
        $minutes_before_date = date_interval_format($diff, "%i");
        $hours_before_date = $days_diff * 24 + $hours_diff;
    }

    return [ $hours_before_date, $minutes_before_date ];
}

/**
 * Возвращает дополнительное название класса, если до истечения лота осталось меньше часа

 * @param string $date Дата в формате "ГГГГ-ММ-ДД"
 * @return string Массив классов
 */
function return_timer_class($date)
{
    [$hoursLeft] = count_time_diff($date);

    if ($hoursLeft < 1) {
        return " timer--finishing";
    }

    return "";
}

/**
 * Возвращает массив данных о том, сколько времени осталось до истечения лота, отформатированных с ведущими нулями

 * @param string $date Дата в формате "ГГГГ-ММ-ДД"
 * @return array Возвращённые данные
 */
function print_timer($date)
{
    $time = count_time_diff($date);

    foreach($time as &$num) {
        $num = str_pad($num, 2, "0", STR_PAD_LEFT);
    }

    return $time;
}

/**
 * Возвращает массив всех данных из таблицы category

 * @param mysqli $con Подключение к ДБ
 * @return array Массив данных из таблицы category
 */
function getCategories($con)
{
    $data = [];

    $sql = "SELECT * FROM category";
    $result = mysqli_query($con, $sql);

    if ($result) {
        $data = mysqli_fetch_all($result, MYSQLI_ASSOC);
    }

    return $data;
}

/**
 * Возвращает результат запроса к БД с данными таблицы лотов, категорией лота и его текущей ценой

 * @param mysqli $con Подключение к ДБ
 * @param string $condition Дополнительное условие запроса к БД, по умолчанию равно пустой строке
 * @return mysqli_result Результат запроса к БД
 */
function prepareLotsQuery($con, $condition = "")
{
    $sql = "SELECT 
                  lots.*,            
                  IFNULL(lots.current_price, lots.start_price) as price
            FROM (
                    SELECT l.*,
                           c.name as category,
                           (   
                               SELECT value
                               FROM bid as b
                               WHERE b.lot_id = l.id
                               ORDER BY b.value DESC
                               LIMIT 1
                           ) as current_price
                    FROM lot as l
                    JOIN category as c
                    ON l.category_id = c.id "
                    . $condition .
                ") as lots ";

    $result = mysqli_query($con, $sql);
    return $result;
}

/**
 * Возвращает массив с данными открытых лотов из таблицы lot, название категории, к которой принадлежит лот, и его текущую цену с учётом ставок

 * @param mysqli $con Подключение к ДБ
 * @return array Массив данных из таблицы lot
 */
function getActiveLots($con)
{
    $data = [];
    $condition = "WHERE l.date_expire > NOW() ORDER BY l.date_expire ASC";
    $result = prepareLotsQuery($con, $condition);

    if($result) {
        $data = mysqli_fetch_all($result, MYSQLI_ASSOC);
    }

    return $data;
}

/**
 * Возвращает числовое значение переменной

 * @param string $query Параметр, получаемый из строки запроса
 * @return int Числовое значение переменной
 */
function returnIntFromQuery($query)
{
   return intval($query);
}

/**
 * Возвращает значение указанного параметра из строки запроса

 * @param string $param Параметр, получаемый из строки запроса
 * @return string Значение параметра
 */
function getParamFromQuery($param)
{
    if ($param === "id") {
        return returnIntFromQuery($_GET[$param]) ?? "";
    }

    return $_GET[$param] ?? "";
}

/**
 * Возвращает массив с данными лота, соответствующего переданному id, название категории, к которой принадлежит лот, и его текущую цену с учётом ставок

 * @param mysqli $con Подключение к ДБ
 * @param string $id Идентификатор лота
 * @return array Массив данных из таблицы lot
 */
function getLotById($con, $id)
{
    $data = [];
    $condition = "WHERE l.id = $id";
    $result = prepareLotsQuery($con, $condition);

    if($result) {
        $data = mysqli_fetch_assoc($result);
    }

    return $data;
}

/**
 * Возвращает значения прежде заполненных полей из массива POST

 * @param string $name Имя поля
 * @return string Значение поля
 */
function getPostVal($name)
{
    return $_POST[$name] ?? "";
}

/**
 * Возвращает массив ошибок, полученных после валидации полей формы "Добавить новый лот"

 * @param array $categories Массив категорий, к которым может принадлежать лот
 * @return array Массив ошибок
 */
function validateLot($categories)
{
    $errors = [];
    $cats_ids = array_column($categories, "id");

    $rules = [
        "lot-name" => function() {
            return validateFilled("lot-name");
        },
        "category" => function() use ($cats_ids) {
            return validateCategory("category", $cats_ids);
        },
        "message" => function() {
            return validateFilled("message");
        },
        "lot-rate" => function() {
            if (!validateFilled("lot-rate")) {
                return isNumPositiveInt("lot-rate");
            }

            return validateFilled("lot-rate");
        },
        "lot-step" => function() {
            if (!validateFilled("lot-step")) {
                return isNumPositiveInt("lot-step");
            }

            return validateFilled("lot-step");
        },
        "lot-date" => function() {
            if (!validateFilled("lot-date")) {
                return validateDate("lot-date");
            }

            return validateFilled("lot-date");
        }
    ];

    foreach ($_POST as $key => $value) {
        if (isset($rules[$key])) {
            $rule = $rules[$key];
            $errors[$key] = $rule();
        }
    };

    $errors["lot-img"] = validateImage("lot-img");

    $errors = array_filter($errors);

    return $errors;
}

/**
 * Возвращает текстовую строку, которая выводится при ошибки валидации поля "Категория", или null, если ошибки нет

 * @param string $field Имя поля в массиве $_POST
 * @param array $cats_ids Массив id существующих категорий
 * @return string Текст ошибки или null
 */
function validateCategory($field, $cats_ids)
{
    $id = $_POST[$field];

    if (!in_array($id, $cats_ids)) {
        return "Укажите существующую категорию";
    }

    return null;
}

/**
 * Возвращает текст ошибки, если обязательное поле формы не заполнено, или null, если ошибки нет

 * @param string $field Имя поля в массиве $_POST
 * @return string Текст ошибки или null
 */
function validateFilled($field)
{
    if (empty($_POST[$field])) {
        return "Это поле должно быть заполнено";
    }

    return null;
}

/**
 * Возвращает текст ошибки, если в поле формы указано не целое положительное число, или null, если ошибки нет

 * @param string $field Имя поля в массиве $_POST
 * @return string Текст ошибки или null
 */
function isNumPositiveInt($field)
{
    if (!ctype_digit($_POST[$field]) || $_POST[$field] <= 0) {
        return "Введите целое положительное число";
    }

    return null;
}

/**
 * Возвращает текст ошибки, если дата, указанная в поле формы, не соответствует формату "ГГГГ-ММ-ДД", или дата не больше текущей на сутки, или null, если ошибки нет

 * @param string $field Имя поля в массиве $_POST
 * @return string Текст ошибки или null
 */
function validateDate($field)
{
    if (is_date_valid($_POST[$field])) {
        [$hoursLeft] = count_time_diff($_POST[$field]);

        if ($hoursLeft < 24) {
            return "Указанная дата должна быть больше текущей хотя бы на одни сутки";
        }
    }
    else {
        return "Введите дату в формате ГГГГ-ММ-ДД";
    }
}

/**
 * Возвращает текст ошибки, если изображение не загружено или не соответствует необходимому формату

 * @param string $field Имя поля в массиве $_FILES
 * @return string Текст ошибки или null
 */
function validateImage($field)
{
    if (!empty($_FILES[$field]["name"])) {
        return validateImageFormat($_FILES[$field]);
    }

    return "Загрузите изображение лота";
}

/**
 * Возвращает текст ошибки, если формат картинки не соответствует jpg или png, или null, если ошибки нет

 * @param array $file Данные файла из массива $_FILES
 * @return string Текст ошибки или null
 */
function validateImageFormat($file)
{
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
function moveFile($file)
{
    $file_name = $file["name"];
    $file_path = __DIR__ . '/uploads/';
    $file_url = '/uploads/' . $file_name;
    move_uploaded_file($file["tmp_name"], $file_path . $file_name);

    return $file_url;
}

/**
 * Записывает данные в таблицу ДБ и возвращает id добавленной строки

 * @param mysqli $con Подключение к ДБ
 * @param string $sql Строка запроса к ДБ
 * @param array $data Массив значений, которые передаются в подготовленное выражение, по умолчанию пустой
 * @return string id добавленной строки
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
 * @return string id лота
 */
function insertLot($con)
{
    $name = $_POST["lot-name"];
    $description = $_POST["message"];
    $image_url = moveFile($_FILES["lot-img"]);
    $start_price = $_POST["lot-rate"];
    $date_expire = $_POST["lot-date"];
    $bid_step = $_POST["lot-step"];
    $category_id = $_POST["category"];
    $sql = "INSERT INTO lot (name, description, image_url, start_price, date_expire, bid_step, category_id, seller_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

    $result = db_insert_data($con, $sql, [$name, $description, $image_url, $start_price, $date_expire, $bid_step, $category_id, 2]);

    if (!$result) {
        $error = mysqli_error($con);
        return "Ошибка MySQL: " . $error;
    }

    return $result;
}
