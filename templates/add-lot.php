<main>
    <?=$nav; ?>
    <form class="form form--add-lot container<?=isset($errors) ? " form--invalid" : ""; ?>" action="add.php" method="post" enctype="multipart/form-data">
        <h2>Добавление лота</h2>
        <div class="form__container-two">
            <div class="form__item<?=isset($errors["lot-name"]) ? " form__item--invalid" : ""; ?>">
                <label for="lot-name">Наименование <sup>*</sup></label>
                <input id="lot-name" type="text" name="lot-name" placeholder="Введите наименование лота" value="<?=strip_tags($lot["lot-name"]); ?>">
                <span class="form__error"><?=$errors["lot-name"] ?? ""; ?></span>
            </div>
            <div class="form__item<?=isset($errors["category"]) ? " form__item--invalid" : ""; ?>">
                <label for="category">Категория <sup>*</sup></label>
                <select id="category" name="category">
                    <option value="select">Выберите категорию</option>
                    <?php foreach($categories as $category): ?>
                        <option <?php if ($lot["category"] === $category["id"]) print("selected");?> value="<?=$category["id"]; ?>"><?=$category["name"]; ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="form__error"><?=$errors["category"] ?? ""; ?></span>
            </div>
        </div>
        <div class="form__item form__item--wide<?=isset($errors["message"]) ? " form__item--invalid" : ""; ?>">
            <label for="message">Описание <sup>*</sup></label>
            <textarea id="message" name="message" placeholder="Напишите описание лота"><?=strip_tags($lot["message"]); ?></textarea>
            <span class="form__error"><?=$errors["message"] ?? ""; ?></span>
        </div>
        <div class="form__item form__item--file<?=isset($errors["lot-img"]) ? " form__item--invalid" : ""; ?>">
            <label>Изображение <sup>*</sup></label>
            <div class="form__input-file">
                <input class="visually-hidden" type="file" id="lot-img" name="lot-img">
                <label for="lot-img">
                    Добавить
                </label>
                <span class="form__error"><?=$errors["lot-img"] ?? ""; ?></span>
            </div>
        </div>
        <div class="form__container-three">
            <div class="form__item form__item--small<?=isset($errors["lot-rate"]) ? " form__item--invalid" : ""; ?>">
                <label for="lot-rate">Начальная цена <sup>*</sup></label>
                <input id="lot-rate" type="text" name="lot-rate" placeholder="0" value="<?=strip_tags($lot["lot-rate"]); ?>">
                <span class="form__error"><?=$errors["lot-rate"] ?? ""; ?></span>
            </div>
            <div class="form__item form__item--small<?=isset($errors["lot-step"]) ? " form__item--invalid" : ""; ?>">
                <label for="lot-step">Шаг ставки <sup>*</sup></label>
                <input id="lot-step" type="text" name="lot-step" placeholder="0" value="<?=strip_tags($lot["lot-step"]); ?>">
                <span class="form__error"><?=$errors["lot-step"] ?? ""; ?></span>
            </div>
            <div class="form__item<?=isset($errors["lot-date"]) ? " form__item--invalid" : ""; ?>">
                <label for="lot-date">Дата окончания торгов <sup>*</sup></label>
                <input class="form__input-date" id="lot-date" type="text" name="lot-date" placeholder="Введите дату в формате ГГГГ-ММ-ДД" value="<?=strip_tags($lot["lot-date"]); ?>">
                <span class="form__error"><?=$errors["lot-date"] ?? ""; ?></span>
            </div>
        </div>
        <span class="form__error form__error--bottom">Пожалуйста, исправьте ошибки в форме.</span>
        <button type="submit" class="button">Добавить лот</button>
    </form>
</main>
