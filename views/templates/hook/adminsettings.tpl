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
<form action="{$current_url|escape:'htmlall':'UTF-8'}" method="post">
    <fieldset class="width2">
        <legend><img src="../img/admin/cog.gif" alt="" class="middle" />{l s='Settings' mod='kiyohcustomerreview'}</legend>

        <label>{l s='Module Version' mod='kiyohcustomerreview'}</label>
        <div class="margin-form">
            <p>{$version|escape:'htmlall':'UTF-8'}</p>
        </div>


        <label>{l s='Select Server' mod='kiyohcustomerreview'}</label>
        <div class="margin-form" id="kiyoh_server">
            <select name="server" id="server">
                {html_options options=$servers selected=$server}
            </select>
        </div>

        <div class="newfields">
            <label>{l s='Hash' mod='kiyohcustomerreview'}</label>
            <div class="margin-form">
                <input type="text" name="hash" value="{$hash|escape:'htmlall':'UTF-8'}" />
                <p class="clear">{l s='Enter here the connector hash' mod='kiyohcustomerreview'}</p>
            </div>

            <label>{l s='Location Id' mod='kiyohcustomerreview'}</label>
            <div class="margin-form">
                <input type="text" name="locationid" value="{$locationid|escape:'htmlall':'UTF-8'}" />
                <p class="clear">{l s='Enter here the location id' mod='kiyohcustomerreview'}</p>
            </div>
        </div>

        <div class="oldfields">
            <label>{l s='Enter Connector' mod='kiyohcustomerreview'}</label>
            <div class="margin-form">
                <input type="text" name="connector" value="{$connector|escape:'htmlall':'UTF-8'}" />
                <p class="clear">{l s='Enter here the KiyOh Connector Code from your KiyOh Account.' mod='kiyohcustomerreview'}</p>
            </div>
            <label>{l s='Company Id' mod='kiyohcustomerreview'}</label>
            <div class="margin-form">
                <input type="text" name="company_id" value="{$company_id|escape:'htmlall':'UTF-8'}" />
                <p class="clear">{l s='Enter here your "company id" as registered in your KiyOh account' mod='kiyohcustomerreview'}</p>
            </div>
            <label>{l s='Company Email' mod='kiyohcustomerreview'}</label>
            <div class="margin-form">
                <input type="text" name="company_email" value="{$company_email|escape:'htmlall':'UTF-8'}" />
                <p class="clear">{l s='Enter here your "company email address" as registered in your KiyOh account. Not the "user email address"! ' mod='kiyohcustomerreview'}</p>
            </div>
        </div>

        <label>{l s='Enter delay' mod='kiyohcustomerreview'}</label>
        <div class="margin-form">
            <input type="text" name="delay" value="{$delay|escape:'htmlall':'UTF-8'}" />
            <p class="clear">{l s='Enter here the delay(number of days) after which you would like to send review invite email to your customer. This delay applies after customer event(order status change - to be selected at next option). You may enter 0 to send review invite email immediately after customer event(order status change).' mod='kiyohcustomerreview'}</p>
        </div>

        <label>{l s='Show rating' mod='kiyohcustomerreview'}</label>
        <div class="margin-form">
            <select name="show_rating">
                {html_options options=$show_rating_aval selected=$show_rating}
            </select>
        </div>

        <label>{l s='Order Status Change Event' mod='kiyohcustomerreview'}</label>
        <div class="margin-form">
            <select name="order_status[]" id="order_status" multiple>
                {html_options options=$allorder_statuses selected=$order_status}
            </select>
            <p class="clear">{l s='Enter here the event after which you would like to send review invite email to your customer.' mod='kiyohcustomerreview'}</p>
        </div>

        <div id="kiyoh_language" class="oldfields">
            <label>{l s='Email template language' mod='kiyohcustomerreview'}</label>
            <div class="margin-form">
                <select name="language" id="language">
                    {html_options options=$langs selected=$language}
                </select>
            </div>
        </div>
        <div id="kiyoh_language1" class="newfields">
            <label>{l s='Language' mod='kiyohcustomerreview'}</label>
            <div class="margin-form">
                <input type="text" name="language1" value="{$language1|escape:'htmlall':'UTF-8'}" />
            </div>
        </div>
        <script type="text/javascript">
            jQuery(document).ready(function() {
                jQuery("#kiyoh_server select[name=server]").on("change",function(){
                    var value = jQuery(this).val();
                    if (value=="klantenvertellen.nl" || value=="newkiyoh.com"){
                        jQuery(".newfields").show();
                        jQuery(".oldfields").hide();
                    } else {
                        jQuery(".newfields").hide();
                        jQuery(".oldfields").show();
                    }
                    if (value=="kiyoh.com"){
                        jQuery("#kiyoh_language").show();
                    } else {
                        jQuery("#kiyoh_language").hide();
                    }
                });
                jQuery("#kiyoh_server select[name=server]").trigger("change");
            });
        </script>
        <div class="margin-form"><input type="submit" name="submitKiyoh" value="{l s='Save' mod='kiyohcustomerreview'}" class="button" /></div>
    </fieldset>
</form>