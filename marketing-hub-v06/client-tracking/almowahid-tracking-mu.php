<?php
/** Keep website WhatsApp attribution aligned with the marketing CRM. */
if(!defined('ABSPATH'))exit;
add_action('wp_head',static function():void{
 if(is_admin())return;
 echo '<script>window.HORIZONS_WA_ATTR={clientId:"cl_3ea5ae96e05c6b",businessNumber:"+966537033347",timeoutMs:2500};</script>';
 echo '<script defer src="https://marketing.hositee.com/horizons-wa-attribution.js?v=20261005-2"></script>';
},20);
