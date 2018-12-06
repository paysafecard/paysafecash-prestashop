<style>
	.paysafecash_description{
		color:black;
		font-size: 12px;
		padding-left: 20px;
	}
</style>
<div class="row">
	<div class="col-xs-12 col-md-12">
		<p class="payment_module" id="paysafecash_payment_button">
				<a href="{$link->getModuleLink('paysafecash_gateway', 'redirect', array(), true)|escape:'htmlall':'UTF-8'}" title="{l s='Pay with my payment module' mod='paysafecash'}">
					<img src="{$module_dir|escape:'htmlall':'UTF-8'}/logo_long.png" alt="{l s='Pay with my payment module' mod='paysafecash'}"  />
				</a>
			<div class="paysafecash_description">{l s='Paysafecash is a cash payment option. Generate a QR/barcode and pay at a nearby shop.More information and our payment points can be found at' mod='paysafecash'} <a href="https://www.paysafecash.com" target="_blank">www.paysafecash.com</a></div>
		</p>
	</div>
</div>
