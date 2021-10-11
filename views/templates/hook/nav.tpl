{*
* 2014 Interactivated.me
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
*
*  @author    Interactivated <contact@interactivated.me>
*  @copyright 2014 Interactivated.me
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*
*}
<div class="kiyoh-shop-snippets" style="{$show_rating|escape:'htmlall':'UTF-8'}">
    <div class="rating-box">
        <div class="rating" style="width:{$rating_percentage|escape:'htmlall':'UTF-8'}%"></div>
    </div>
    <div class="kiyoh-schema" itemscope="itemscope" itemtype="http://schema.org/Organization">
        <meta itemprop="name" content="{$storename|escape:'htmlall':'UTF-8'}">
        <div itemprop="aggregateRating" itemscope="itemscope" itemtype="http://schema.org/AggregateRating">
            <meta itemprop="bestRating" content="{$maxrating|escape:'htmlall':'UTF-8'}">
            <p>
                <a href="{$url|escape:'htmlall':'UTF-8'}" target="_blank" class="kiyoh-link">
                    {l s='Rating' mod='kiyohcustomerreview'} <span itemprop="ratingValue">{$rating|escape:'htmlall':'UTF-8'}</span> {l s='out of %s, based on' sprintf=[$maxrating] mod='kiyohcustomerreview'} <span itemprop="ratingCount">{$reviews|escape:'htmlall':'UTF-8'}</span> {l s='customer reviews' mod='kiyohcustomerreview'}
                </a>
            </p>
        </div>
    </div>
</div>
