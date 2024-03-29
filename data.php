<?php
if (!isset($_COOKIE["PHPSESSID"]))
{
  session_start();
}

define("ERROR_DATA_INSERT", "error-data-insert");
define("ERROR_DATA_GET", "error-data-get");
define("ERROR_USER_INSERT", "error-user-insert");
define("ERROR_USER_GET", "error-user-get");
define("ERROR_USER_NOT_AUTH", "error-user-not-auth");
define("ERROR_404", "error-404");

$error_messages = [
    ERROR_DATA_INSERT => "Ошибка добавления данных.",
    ERROR_DATA_GET => "Ошибка получения данных.",
    ERROR_USER_INSERT => "Ошибка регистрации пользователя.",
    ERROR_USER_GET => "Ошибка получения данных пользователя.",
    ERROR_USER_NOT_AUTH => "Этот функционал недоступен для незарегистрированного пользователя.",
    ERROR_404 => "Даннная страница не найдена."
];

$user_id = null;
$user_name = null;
$is_auth = isset($_SESSION["user"]);

if ($is_auth) {
    $user_id = $_SESSION["id"];
    $user_name = $_SESSION["user"];
}

$categories = get_categories($con);
$cats_ids = array_column($categories, "id");

$header = include_template("header.php", [
    "is_auth" => $is_auth,
    "user_name" => $user_name
]);

$nav = include_template("nav.php", [
    "categories" => $categories
]);

$footer = include_template("footer.php", [
    "categories" => $categories
]);

$lot_validators = [
    "lot-name" => function ($data) {
        if (!validate_filled($data, "lot-name")) {
            return is_correct_length($data, "lot-name", 255);
        }
        return validate_filled($data, "lot-name");
    },
    "category" => function ($data) use ($cats_ids) {
        return validate_category($data, "category", $cats_ids);
    },
    "message" => function ($data) {
        return validate_filled($data, "message");
    },
    "lot-rate" => function ($data) {
        if (!validate_filled($data, "lot-rate")) {
            return is_num_positive_int($data, "lot-rate");
        }
        return validate_filled($data, "lot-rate");
    },
    "lot-step" => function ($data) {
        if (!validate_filled($data, "lot-step")) {
            return is_num_positive_int($data, "lot-step");
        }
        return validate_filled($data, "lot-step");
    },
    "lot-date" => function ($data) {
        if (!validate_filled($data, "lot-date")) {
            return validate_date($data, "lot-date");
        }
        return validate_filled($data, "lot-date");
    }
];

$bid_validators = [
    "cost" => function($bid, $lot) {
        if (!validate_filled($bid, "cost")) {
            if(!is_num_positive_int($bid, "cost")) {
                return validate_bid($bid, "cost", $lot);
            };
            return is_num_positive_int($bid, "cost");
        }
        return validate_filled($bid, "lot-date");
    }
];

$user_validators = [
    "name" => function ($data) {
        if (!validate_filled($data, "name")) {
            return is_correct_length($data, "name", 255);
        }
        return validate_filled($data, "lot-name");
    },
    "password" => function ($data) {
        if (!validate_filled($data, "password")) {
            return is_correct_length($data, "password", 64);
        }
        return validate_filled($data, "password");
    },
    "email" => function ($data) {
        if (!validate_filled($data, "email")) {
            if(!is_correct_length($data, "email", 255)) {
                return validate_email($data, "email");
            }
            return is_correct_length($data, "email", 255);            
        }
        return validate_filled($data, "email");
    },
    "message" => function ($data) {
        return validate_filled($data, "message");
    }
];


