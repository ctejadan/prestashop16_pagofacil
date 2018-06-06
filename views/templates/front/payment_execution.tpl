{*
* 2007-2016 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2016 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}

{capture name=path}
    <a href="{$link->getPageLink('order', true, NULL, "step=3")|escape:'html':'UTF-8'}"
       title="{l s='Go back to the Checkout' mod='pagofacil'}">{l s='Checkout' mod='pagofacil'}</a>
    <span class="navigation-pipe">{$navigationPipe}</span>{l s='Credit and debit card' mod='pagofacil'}
{/capture}

<h2>{l s='Order summary' mod='pagofacil'}</h2>

{assign var='current_step' value='payment'}
{include file="$tpl_dir./order-steps.tpl"}

{if $nbProducts <= 0}
    <p class="warning">{l s='Your shopping cart is empty.' mod='pagofacil'}</p>
{else}
    {if $showAllPlatformsInPagoFacil == 'YES'}
        <h3>{l s='Confirm your order to pay with Pago Fácil' mod='pagofacil'}</h3>
        <form action="{$link->getModuleLink('pagofacil', 'validation', [], true)|escape:'html'}" method="post">
            <p>
                <b>{l s='By confirming your order, you will be redirected to the Pago Fácil platform.' mod='pagofacil'}</b>
            </p>
            <p class="cart_navigation" id="cart_navigation">
                <input type="submit" value="{l s='I confirm my order' mod='pagofacil'}" class="exclusive_large"/>
                <a href="{$link->getPageLink('order', true, NULL, "step=3")|escape:'html'}"
                   class="button_large">{l s='Other payment methods' mod='pagofacil'}</a>
            </p>
        </form>
    {else}
        <h3>{l s='Select a payment method to continue' mod='pagofacil'}</h3>
        <form action="{$link->getModuleLink('pagofacil', 'validation', [], true)|escape:'html'}" method="post">

            {foreach from=$result['externalServices'] key=ix item=service}
                <div>
                    {if $ix == 0}
                        <input checked type="radio" name="endpoint" value="{$service['endpoint']}" required>
                    {else}
                        <input type="radio" name="endpoint" value="{$service['endpoint']}" required>
                    {/if}
                    {$service['name']}
                    <img src="{$service['logo_url']}" height="40">
                    <br>
                    <p>{$service['description']}</p>
                </div>
                <br/>
            {/foreach}

            <p>
                <b>{l s='By confirming your order, you will be redirected to the Pago Fácil platform.' mod='pagofacil'}</b>
            </p>

            <p class="cart_navigation" id="cart_navigation">
                <input type="submit" value="{l s='I confirm my order' mod='pagofacil'}" class="exclusive_large"/>
                <a href="{$link->getPageLink('order', true, NULL, "step=3")|escape:'html'}"
                   class="button_large">{l s='Other payment methods' mod='pagofacil'}</a>
            </p>
        </form>
    {/if}
{/if}
