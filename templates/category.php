<main>
    <?=$nav; ?>
    <div class="container">
        <section class="lots">
            <h2>Все лоты в категории <span>«<?=$category_name; ?>»</span></h2>
            <ul class="lots__list">
                <?php if (empty($lots)): ?>
                    <?="<p>Активных лотов нет.</p>"; ?>
                <?php else: ?>
                    <?php foreach ($lots as $lot): ?>
                        <li class="lots__item lot">
                            <div class="lot__image">
                                <img src="<?=$lot["image_url"]; ?>" width="350" height="260" alt="">
                            </div>
                            <div class="lot__info">
                                <span class="lot__category"><?=$lot["category"]; ?></span>
                                <h3 class="lot__title"><a class="text-link" href="/lot.php?<?=http_build_query(["id" => $lot["id"]]); ?>"><?=strip_tags($lot["name"]); ?></a></h3>
                                <div class="lot__state">
                                    <div class="lot__rate">
                                        <?php if (empty($lot["bids_num"])): ?>
                                            <span class="lot__amount">Стартовая цена</span>
                                        <?php else: ?>
                                            <span class="lot__amount"><?=$lot["bids_num"] . " " . get_noun_plural_form($lot["bids_num"], "ставка", "ставки", "ставок"); ?></span>
                                        <?php endif; ?>
                                        <span class="lot__cost"><?=format_price($lot["price"]); ?></span>
                                    </div>
                                    <div class="timer lot__timer<?=return_timer_class($lot, "date_expire"); ?>">
                                        <?=print_timer($lot, "date_expire"); ?>
                                    </div>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
            <?=$pagination_block; ?>
        </section>
    </div>
</main>
