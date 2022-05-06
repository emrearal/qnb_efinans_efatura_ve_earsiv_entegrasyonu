<?php
@ini_set('default_charset', 'UTF-8');
ini_set("soap.wsdl_cache_enabled", "0");

$fisno =$_POST['fisno'];  
$fltaslakno=$_POST['forlogicno']; 
$sql = "SELECT * from faturavedekontkayitlari where fisno=$fisno";
$mysqli = new mysqli($GLOBALS['mysqlurl'],$GLOBALS['mysqlusername'] ,$GLOBALS['mysqlpassword'] ,$GLOBALS['mysqldatabase']);
$mysqli->set_charset("utf8");
if($mysqli->connect_error) {
    exit('Bağlantı hatası');
}
$sonuc = $mysqli->query($sql);
$fisdatasi = $sonuc->fetch_assoc();

if ($sonuc->num_rows == 0) {
    echo("HATA:Kayıt bulunamadı");
    exit();
}
$mysqli -> close();

$fattarih=date("Y-m-d");
$fatsaat=date("H:i:s");

$efaturatutar=$fisdatasi['genelciplaktoplam'];
$faturadovizi=strtoupper($fisdatasi['parabirimi']);
$tlkuru=$fisdatasi['tlkuru'];

$muhatapdetaylari=array();
$muhatapdetaylari=sirketkoducozumle('*'.$fisdatasi['muhatapkodu']);
$faturaisim= $muhatapdetaylari[0];
$faturaadres=$muhatapdetaylari[1];
$faturasehir=$muhatapdetaylari[2];
$faturaulke=$muhatapdetaylari[3];
$vergino=$muhatapdetaylari[5];

$vergiuzun=strlen($vergino);
if($vergiuzun!=11 && $vergiuzun!=10  ) {
    echo ('HATA:VKN ise 10 basamaklı, TCKN ise 11 Basamaklı olmalıdır');
    exit();
}

$WsdlAdres 			= "https://erpefaturatest.cs.com.tr:8043/efatura/ws/connectorService?wsdl";
$WsdlKullaniciAdi 	= "efaturakullaniciadi";
$Wsdlsifre			= "efaturasifre";

$earsivmi=!efaturakullanicisimi($WsdlAdres,$WsdlKullaniciAdi,$Wsdlsifre,$vergino);
$senaryo="TEMELFATURA";   // efaturada TEMELFATURA veya TICARIFATURA olabilir

$uuid=''; 

if($earsivmi) 
{ // earşiv ayarları
    $WsdlAdres 			= "https://earsivtest.efinans.com.tr/earsiv/ws/EarsivWebService?wsdl";
    $WsdlKullaniciAdi 	= "earsivkullaniciadi";
    $Wsdlsifre			= "earsivsifre";
    $senaryo            = "EARSIVFATURA";  // earşivde sadece EARSIVFATURA olabilir
    $uuid               = uuiduret();
}

$vergidairesi= $muhatapdetaylari[4];
$faturaad= substr($faturaisim,0,strpos($faturaisim,' '));
$faturasoyad=substr($faturaisim,(strpos($faturaisim,' '))+1);
$efirmaad="Faturayi Kesen Firma ve Ticaret Limited Şirketi"; // Bizim firmamızın detayları
$efirmaadres="Barış Mah. 3. Oda iş Merkesi";
$efirmavergino="1234567890";
$efirmaverdaire="Beylikdüzü";
$efirmano=" 3/23";
$efirmailce="Beylikdüzü";
$efirmail="İSTANBUL";
$efirmaulke='TÜRKİYE';
$efaturatel= '02128875425';
$efaturaeposta= 'info@samsun.web.tr';
$efaturanot='<cbc:Note>Faturaye eklenecek notlar. Birden fazla olabilir/cbc:Note>';  

$muhatapkodu=$fisdatasi['muhatapkodu']; 
$tkftut=$fisdatasi['geneltkftoplam']; // TKF= tevkifat  kısaltmasıdır
$kdvtut=$fisdatasi['genelkdvtoplam'];// toplam kdv tutari
$ciplakartikdv=$efaturatutar+$kdvtut+$tkftut;   // toplam
$odenecektutar=$ciplakartikdv;
$genelkdvartitkf=$tkftut+$kdvtut;
$satirtoplami= unserialize($fisdatasi['satirtoplami']);
$kdv=unserialize($fisdatasi['kdv']);
$tkf=unserialize($fisdatasi['tkf']);
$aciklama=unserialize($fisdatasi['aciklama']);
$ciplaktutar=unserialize($fisdatasi['ciplaktutar']);
$faturakalemikodu=unserialize($fisdatasi['faturakalemikodu']);
$birimfiyat=unserialize($fisdatasi['birimfiyat']);
$miktar=unserialize($fisdatasi['miktar']);
$kdvkodu=unserialize($fisdatasi['kdvkodu']);
$tkfkodu=unserialize($fisdatasi['tkfkodu']);
$tumfaturakalemleri=faturakalemlerivtgonder();
$faturasatirlari="";
$satirsayisi=count($satirtoplami);
$efaturakdv=$etkf=$tkforani=$istisnakodu=$ztkfkodu=$istisnafaturakalemisayisi=0;

for ($i=0;$i<$satirsayisi;$i++) { // FATURA SATIRI DÖNGÜSÜNE GİRİYORUZ
    $b=str_pad(($i+1), 2, '0', STR_PAD_LEFT);
    $fatkalkod=$faturakalemikodu[$i]-1;
    $kdvorani=$tumfaturakalemleri[$fatkalkod][2];
    
    if (floatval($kdvorani)!=18 && floatval($kdvorani)!=0 ) { // KDV oranı sadece sıfır veya onsekiz olabilir. Bir güvenlik önlemi
        $kdvorani='18';
    }
    $tkforani=$tumfaturakalemleri[$fatkalkod][3];
    $hizmetkodu='('.$faturakalemikodu[$i].')'.$tumfaturakalemleri[$fatkalkod][1];
    $susluciplaktutar=(float)($ciplaktutar[$i]);
    $susluciplaktutar=number_format(($susluciplaktutar), 2, '.', ',');
    $kdvartitkf=floatval($kdv[$i])+floatval($tkf[$i]);
    $satirdatkfvar=''; //$satirdaistisnavar='';
    $istisnakoduaciklamasi=$tevkifatkoduaciklamasi=''; // Açıklama koda göre oluşturulacak.
    $vergituru="
                <cac:TaxCategory> 
                <cbc:Name>KDV</cbc:Name>
                <cac:TaxScheme>
				<cbc:Name>Katma Değer Vergisi</cbc:Name>
				<cbc:TaxTypeCode>0015</cbc:TaxTypeCode>
			   </cac:TaxScheme>";
    
    if (intval($kdvkodu[$i])!=0){ // İstisnalı faturaysa her satıra Kdv istisna kodu ve açıklaması yazılmalı
        switch ($kdvkodu[$i]) {
            case '311':
                $istisnakoduaciklamasi="Uluslararası Taşımacılık İstisnası";
                break;
                
            case '314':
                $istisnakoduaciklamasi="Uluslararası Anlaşmalar Kapsamında İstisnalar";
                break;
                
            case '235':
                $istisnakoduaciklamasi="Transit ve Geçici Depolama İstisnası";
                break;
                
            default :
                $istisnakoduaciklamasi=" kodlu istisna";
        }
        $vergituru="
                <cac:TaxCategory> 
                <cbc:Name>KDV</cbc:Name>
                <cbc:TaxExemptionReasonCode>$kdvkodu[$i]</cbc:TaxExemptionReasonCode>
                <cbc:TaxExemptionReason>$istisnakoduaciklamasi</cbc:TaxExemptionReason>
                <cac:TaxScheme>
				<cbc:Name>KDV</cbc:Name>
                <cbc:TaxTypeCode>0015</cbc:TaxTypeCode>
			</cac:TaxScheme>";
    }
    
    if (intval($tkforani)!=0){  // Tevkifatlı faturaysa tkf kodu ve açıklaması yazılmalı. 
        switch ($tkfkodu[$i]) {
            case '624':
                $tevkifatkoduaciklamasi="Tevkifat Sebebi 624 Yük taşımacılığı Hizmeti";
                $tkforani=20; 
                break;
                
            case '625':
                $tevkifatkoduaciklamasi="Tevkifat Sebebi 625 Ticari Reklam Hizmetleri";
                $tkforani=20;
                break;
                
            case '626':
                $tevkifatkoduaciklamasi="Tevkifat Sebebi 626 Diğer Teslimleri";
                $tkforani=20;
                break;
                
            default :
                $tevkifatkoduaciklamasi="";
        }
    $satirdatkfvar="
    <cac:WithholdingTaxTotal>
    <cbc:TaxAmount currencyID='$faturadovizi'>$tkf[$i]</cbc:TaxAmount>
    <cac:TaxSubtotal>
    <cbc:TaxableAmount currencyID='$faturadovizi'>$kdvartitkf</cbc:TaxableAmount>
    <cbc:TaxAmount currencyID='$faturadovizi'>$tkf[$i]</cbc:TaxAmount>
    <cbc:Percent>20</cbc:Percent>
    <cac:TaxCategory>
    <cac:TaxScheme>
    <cbc:Name>$tevkifatkoduaciklamasi</cbc:Name>
    <cbc:TaxTypeCode>624</cbc:TaxTypeCode>
    </cac:TaxScheme>
    </cac:TaxCategory>
    </cac:TaxSubtotal>
    </cac:WithholdingTaxTotal>";  // satırda tevkifat var ise bunlar eklenecek
   }
    
    if($efaturakdv==0 && intval($kdvorani)!=0) {
        $efaturakdv=$kdvorani; // sadece bir kere atamak için böyle yapıldı
    }
    
    if($etkf==0 && intval($tkforani)!=0) {
        $etkf=$tkforani; // sadece bir kere atamak için böyle yapıldı
    }
    
    if($istisnakodu==0 && intval($kdvkodu[$i])!=0) {
        $istisnakodu=$kdvkodu[$i]; // sadece bir kere atamak için böyle yapıldı
    }
    
    if($ztkfkodu==0 && intval($tkfkodu[$i])!=0) {
        $ztkfkodu=$tkfkodu[$i]; // sadece bir kere atamak için böyle yapıldı
    }
    
    if(intval($kdvkodu[$i])!=0) {
        $istisnafaturakalemisayisi++;
    }
    $faturasatirlari.="
    <cac:InvoiceLine>
    <cbc:ID>$b</cbc:ID>
    <cbc:InvoicedQuantity unitCode='NIU'>$miktar[$i]</cbc:InvoicedQuantity>
    <cbc:LineExtensionAmount currencyID='TRY'>$ciplaktutar[$i]</cbc:LineExtensionAmount>
    <cac:TaxTotal>
		<cbc:TaxAmount currencyID='$faturadovizi'>$kdvartitkf</cbc:TaxAmount>
		<cac:TaxSubtotal>
			<cbc:TaxAmount currencyID='$faturadovizi'>$kdvartitkf</cbc:TaxAmount>
			 <cbc:Percent>$kdvorani</cbc:Percent>
             $vergituru
             </cac:TaxCategory>
		</cac:TaxSubtotal>
	</cac:TaxTotal>
    $satirdatkfvar
    <cac:Item>
    <cbc:Description>$aciklama[$i]</cbc:Description>
     <cbc:Name>$hizmetkodu </cbc:Name>
    </cac:Item>
    <cac:Price>
         <cbc:PriceAmount currencyID='$faturadovizi'>$birimfiyat[$i]</cbc:PriceAmount>
    </cac:Price>
    </cac:InvoiceLine>";
} // fatura satırları DÖNGÜSÜ SONU;

$faturatipi='SATIS'; // varsayılan durum istisnasız tevkifatsız satış faturası
$tkfvarsa='';
$vergituru="
                <cac:TaxCategory>
                <cbc:Name>KDV</cbc:Name>
                <cac:TaxScheme>
				<cbc:Name>Katma Değer Vergisi</cbc:Name>
				<cbc:TaxTypeCode>0015</cbc:TaxTypeCode>
			   </cac:TaxScheme>";

if (intval($istisnakodu)!=0){ // İstisnalı faturaysa Kdv istisna kodu ve açıklaması en dibe yazılmalı
    $istisnakoduaciklamasi=($istisnakoduaciklamasi=='') ? 'kodlu istisna' :$istisnakoduaciklamasi;
    $vergituru="
                <cac:TaxCategory>
                <cbc:Name>KDV</cbc:Name>
                <cbc:TaxExemptionReasonCode>$istisnakodu</cbc:TaxExemptionReasonCode>
                <cbc:TaxExemptionReason>$istisnakoduaciklamasi</cbc:TaxExemptionReason>
                <cac:TaxScheme>
				<cbc:Name>KDV</cbc:Name>
                <cbc:TaxTypeCode>0015</cbc:TaxTypeCode>
			</cac:TaxScheme>";
    
    $faturatipi="ISTISNA";
}
if ($ztkfkodu!=0){  //  Tevkifatlı faturaysa tevkifat detayları en dibe yazılmalı
    $tkfvarsa="
    <cac:WithholdingTaxTotal>
    <cbc:TaxAmount currencyID='$faturadovizi'>$tkftut</cbc:TaxAmount>
    <cac:TaxSubtotal>
    <cbc:TaxableAmount currencyID='$faturadovizi'>$efaturatutar</cbc:TaxableAmount>
    <cbc:TaxAmount currencyID='$faturadovizi'>$tkftut</cbc:TaxAmount>
    <cbc:Percent>20</cbc:Percent>
    <cac:TaxCategory>
    <cac:TaxScheme>
    <cbc:Name>$tevkifatkoduaciklamasi</cbc:Name>
    <cbc:TaxTypeCode>$tkfkodu[0]</cbc:TaxTypeCode>
    </cac:TaxScheme>
    </cac:TaxCategory>
    </cac:TaxSubtotal>
    </cac:WithholdingTaxTotal>";
    
    $faturatipi="TEVKIFAT";
    $odenecektutar-=$tkftut;
}

if($vergiuzun==10) {  // firma ise 
    $verdeger="VKN";
    $kisi="";
    $faturakisi="<cac:PartyName> 
                     <cbc:Name>$faturaisim</cbc:Name>
                </cac:PartyName>" ;
} else {  // kişi ise
    $verdeger="TCKN";
    $faturakisi="";
    $kisi="<cac:Person>
            <cbc:FirstName>$faturaad</cbc:FirstName>
            <cbc:FamilyName>$faturasoyad</cbc:FamilyName>
            </cac:Person>";
}

$xmldosyasi="<Invoice xmlns='urn:oasis:names:specification:ubl:schema:xsd:Invoice-2' xmlns:cac='urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2' xmlns:cbc='urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2' xmlns:ccts='urn:un:unece:uncefact:documentation:2' xmlns:ds='http://www.w3.org/2000/09/xmldsig#' xmlns:ext='urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2' xmlns:qdt='urn:oasis:names:specification:ubl:schema:xsd:QualifiedDatatypes-2' xmlns:ubltr='urn:oasis:names:specification:ubl:schema:xsd:TurkishCustomizationExtensionComponents' xmlns:udt='urn:un:unece:uncefact:data:specification:UnqualifiedDataTypesSchemaModule:2' xmlns:xades='http://uri.etsi.org/01903/v1.3.2#' xmlns:xsi='http://www.w3.org/2001/XMLSchema-instance' xsi:schemaLocation='urn:oasis:names:specification:ubl:schema:xsd:Invoice-2 UBL-Invoice-2.1.xsd'>
    <cbc:UBLVersionID>2.1</cbc:UBLVersionID>
    <cbc:CustomizationID>TR1.2</cbc:CustomizationID>
    <cbc:ProfileID>$senaryo</cbc:ProfileID>
    <cbc:ID>$fltaslakno</cbc:ID>
    <cbc:CopyIndicator>false</cbc:CopyIndicator>
    <cbc:UUID>$uuid</cbc:UUID>
    <cbc:IssueDate>$fattarih</cbc:IssueDate>
    <cbc:IssueTime>$fatsaat</cbc:IssueTime>
    <cbc:InvoiceTypeCode>$faturatipi</cbc:InvoiceTypeCode>
    $efaturanot
    <cbc:DocumentCurrencyCode>$faturadovizi</cbc:DocumentCurrencyCode>
    <cbc:PricingCurrencyCode>$faturadovizi</cbc:PricingCurrencyCode>
    <cbc:LineCountNumeric>$satirsayisi</cbc:LineCountNumeric>
    <cac:Signature>
        <cbc:ID schemeID='VKN_TCKN'>3250566851</cbc:ID>
        <cac:SignatoryParty>
            <cac:PartyIdentification>
                <cbc:ID schemeID='VKN'>3250566851</cbc:ID>
            </cac:PartyIdentification>
            <cac:PostalAddress>
                <cbc:CitySubdivisionName>ŞİŞLİ</cbc:CitySubdivisionName>
                <cbc:CityName>İSTANBUL</cbc:CityName>
                <cac:Country>
                    <cbc:Name>TÜRKİYE</cbc:Name>
                </cac:Country>
            </cac:PostalAddress>
        </cac:SignatoryParty>
        <cac:DigitalSignatureAttachment>
            <cac:ExternalReference>
                <cbc:URI>#Signature_</cbc:URI>
            </cac:ExternalReference>
        </cac:DigitalSignatureAttachment>
    </cac:Signature>
    <cac:AccountingSupplierParty>
        <cac:Party>
            <cac:PartyIdentification>
                <cbc:ID schemeID='VKN'>$efirmavergino</cbc:ID>
            </cac:PartyIdentification>
            <cac:PartyName>
                <cbc:Name>$efirmaad</cbc:Name>
            </cac:PartyName>
            <cac:PostalAddress>
                <cbc:StreetName>$efirmaadres</cbc:StreetName>
                <cbc:BuildingNumber>$efirmano</cbc:BuildingNumber>
                <cbc:CitySubdivisionName>$efirmailce</cbc:CitySubdivisionName>
                <cbc:CityName>$efirmail / $efirmaulke</cbc:CityName>
                <cbc:PostalZone></cbc:PostalZone>
                <cac:Country>
                    <cbc:Name>$efirmaulke</cbc:Name>
                </cac:Country>
            </cac:PostalAddress>
            <cac:PartyTaxScheme>
                <cac:TaxScheme>
                    <cbc:Name>$efirmaverdaire</cbc:Name>
                </cac:TaxScheme>
            </cac:PartyTaxScheme>
            <cac:Contact>
                <cbc:Telephone>$efaturatel</cbc:Telephone>
                <cbc:ElectronicMail>$efaturaeposta</cbc:ElectronicMail>
            </cac:Contact>
        </cac:Party>
    </cac:AccountingSupplierParty>
    
    <cac:AccountingCustomerParty>
        <cac:Party>
            <cac:PartyIdentification>
                <cbc:ID schemeID='$verdeger'>$vergino</cbc:ID>
            </cac:PartyIdentification>
            $faturakisi
            <cac:PostalAddress>
                 <cbc:StreetName>$faturaadres</cbc:StreetName>
                <cbc:BuildingNumber>$efirmano</cbc:BuildingNumber>
                <cbc:CitySubdivisionName></cbc:CitySubdivisionName>
                <cbc:CityName>$faturasehir / $faturaulke</cbc:CityName>
                <cbc:PostalZone></cbc:PostalZone>
                <cac:Country>
                    <cbc:Name>$faturaulke</cbc:Name>
                </cac:Country>
            </cac:PostalAddress>
            <cac:PartyTaxScheme>
                <cac:TaxScheme>
                    <cbc:Name>$vergidairesi</cbc:Name>
                </cac:TaxScheme>
            </cac:PartyTaxScheme>
            <cac:Contact>
                <cbc:Telephone></cbc:Telephone>
            </cac:Contact>
        $kisi
        </cac:Party>
      </cac:AccountingCustomerParty>
<cac:PricingExchangeRate>
        <cbc:SourceCurrencyCode>$faturadovizi</cbc:SourceCurrencyCode>
        <cbc:TargetCurrencyCode>TRY</cbc:TargetCurrencyCode>
        <cbc:CalculationRate>$tlkuru</cbc:CalculationRate>
</cac:PricingExchangeRate>
   <cac:TaxTotal>
        <cbc:TaxAmount currencyID='TRY'>$ciplakartikdv</cbc:TaxAmount>
        <cac:TaxSubtotal>
            <cbc:TaxableAmount currencyID='$faturadovizi'>$efaturatutar</cbc:TaxableAmount>
            <cbc:TaxAmount currencyID='$faturadovizi'>$genelkdvartitkf</cbc:TaxAmount>
            <cbc:Percent>$kdvorani</cbc:Percent>
            $vergituru
            </cac:TaxCategory>
         </cac:TaxSubtotal>
    </cac:TaxTotal>
    $tkfvarsa
    <cac:LegalMonetaryTotal>
        <cbc:LineExtensionAmount currencyID='$faturadovizi'>$efaturatutar</cbc:LineExtensionAmount>
        <cbc:TaxExclusiveAmount currencyID='$faturadovizi'>$efaturatutar</cbc:TaxExclusiveAmount>
        <cbc:TaxInclusiveAmount currencyID='$faturadovizi'>$odenecektutar</cbc:TaxInclusiveAmount>
        <cbc:AllowanceTotalAmount currencyID='$faturadovizi'>0</cbc:AllowanceTotalAmount>
        <cbc:PayableAmount currencyID='$faturadovizi'>$odenecektutar</cbc:PayableAmount>
    </cac:LegalMonetaryTotal>
$faturasatirlari
</Invoice>";

$hash = md5($xmldosyasi);

faturaGonder($WsdlAdres,$WsdlKullaniciAdi,$Wsdlsifre,$fltaslakno,$efirmavergino,$uuid,$xmldosyasi,$hash);  // ÇALIŞTIR

                                                        //////FONKSİYONLAR////////
   function uuiduret() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
	
	function soapClientWSSecurityHeader($user, $password){
	    $tm_created = gmdate('Y-m-d\TH:i:s\Z');
	    $tm_expires = gmdate('Y-m-d\TH:i:s\Z', gmdate('U') + 180);
	    $simple_nonce = mt_rand();
	    $encoded_nonce = base64_encode($simple_nonce);
	    $ns_wsse = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd';
	    $ns_wsu = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd';
	    $password_type = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordDigest';
	    $encoding_type = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-soap-message-security-1.0#Base64Binary';
	    $root = new SimpleXMLElement('<root/>');
	    $security = $root->addChild('wsse:Security', null, $ns_wsse);
	    $timestamp = $security->addChild('wsu:Timestamp', null, $ns_wsu);
	    $timestamp->addAttribute('wsu:Id', 'Timestamp-28');
	    $timestamp->addChild('wsu:Created', $tm_created, $ns_wsu);
	    $timestamp->addChild('wsu:Expires', $tm_expires, $ns_wsu);
	    $usernameToken = $security->addChild('wsse:UsernameToken', null, $ns_wsse);
	    $usernameToken->addChild('wsse:Username', $user, $ns_wsse);
	    $usernameToken->addChild('wsse:Password', $password, $ns_wsse)->addAttribute('Type', $password_type);
	    $usernameToken->addChild('wsse:Nonce', $encoded_nonce, $ns_wsse)->addAttribute('EncodingType', $encoding_type);
	    $usernameToken->addChild('wsu:Created', $tm_created, $ns_wsu);
	    $root->registerXPathNamespace('wsse', $ns_wsse);
	    $full = $root->xpath('/root/wsse:Security');
	    $auth = $full[0]->asXML();
	    return new SoapHeader($ns_wsse, 'Security', new SoapVar($auth, XSD_ANYXML), true);
	}
	
	function efaturakullanicisimi($WsdlAdres,$WsdlKullaniciAdi,$Wsdlsifre,$vergiTcKimlikNo) {
	    try {
	        $client = new SoapClient($WsdlAdres);
	        $client->__setSoapHeaders(soapClientWSSecurityHeader($WsdlKullaniciAdi,$Wsdlsifre));
	        $result=$client->efaturaKullanicisi(
	            array( 'vergiTcKimlikNo'=>$vergiTcKimlikNo));
	    }
	    catch(Exception $e) {
	        die($e->getMessage());
	    }
	    return $result->return;
   }
			
   function faturaGonder($WsdlAdres,$WsdlKullaniciAdi,$Wsdlsifre,$belgeNo,$vergiTcKimlikNo,$uuid,$xmldosyasi,$hash) {
	     
	    if($uuid!='') {//////////// EARŞİV İSE ///////////////////
	        try {
	            $client = new SoapClient($WsdlAdres);
	            $client->__setSoapHeaders(soapClientWSSecurityHeader($WsdlKullaniciAdi,$Wsdlsifre));
	            
	            $result = $client->faturaOlustur(array(
	                'input'=>'{"donenBelgeFormati":3,
                         "goruntuOlusturulsunMu":1,
                         "islemId":"'.$uuid.'",
                         "vkn":"'.$vergiTcKimlikNo.'",
                         "sube":"DFLT",
                         "kasa":"DFLT",
                         "numaraVerilsinMi":0,
                         "sablonAdi": "eArşiv.xslt"}',
	                'fatura'=>array('belgeFormati'=>'UBL','belgeIcerigi'=>''.$xmldosyasi.'')));
	         	            
	         foreach($result as $value) {} 
	            
	         $durumkodu=$value->resultCode;
	         $durumaciklamasi=$value->resultText;
	            
	            if ($durumkodu!='AE00000') {
	                exit("HATA : Fatura Numarası Dönmedi, Fatura Kesilmedi Hata Açıklaması:$durumaciklamasi");
	            }
	            
	            $efatno=$GLOBALS['fltaslakno'];
	            $fisno=$GLOBALS['fisno'];
	            kesilmisefaturayiveritabaninakaydet($efatno,$uuid,$fisno);
	          }
	        catch (SoapFault $e) {
	            exit($e->getMessage());
	        }
	        
	    }else{            //////////// EFATURA İSE /////////////
	    try {
	        $client = new SoapClient($WsdlAdres);
	        $client->__setSoapHeaders(soapClientWSSecurityHeader($WsdlKullaniciAdi,$Wsdlsifre));
	        $result = $client->belgeGonder(
	            array(
	                'belgeNo'                   => $belgeNo,
	                'vergiTcKimlikNo'           => $vergiTcKimlikNo,
	                'belgeTuru'                 => 'FATURA_UBL',
	                'veri'                      => ''.$xmldosyasi.'',
	                'belgeHash'                 => ''.$hash.'',
	                'mimeType'                  => 'application/xml',
	                'belgeVersiyon'             => '3.0'
	            ));
	    }
	    catch(Exception $e) {
	        die($e->getMessage());
	    }
	    eFaturaDurumNe($WsdlAdres,$WsdlKullaniciAdi,$Wsdlsifre,$result->belgeOid,$vergiTcKimlikNo,);
	  }
	}// efatura else'i sonu
	
	function eFaturaDurumNe($WsdlAdres,$WsdlKullaniciAdi,$Wsdlsifre,$belgeOid,$vergiTcKimlikNo) {
	    sleep(10);
	    try {
	        $client = new SoapClient($WsdlAdres);
	        $client->__setSoapHeaders(soapClientWSSecurityHeader($WsdlKullaniciAdi,$Wsdlsifre));
	        $result = $client->gidenBelgeDurumSorgula(
	            array(
	                'vergiTcKimlikNo'           => $vergiTcKimlikNo,
	                'belgeOid'                  =>  $belgeOid,
	            ));
	    }
	    catch(Exception $e) {
	        die($e->getMessage());
	    }
	    $efatno=$result->return->belgeNo;
	    $efatid=$result->return->ettn;
	    $fisno=$GLOBALS['fisno'];
	    
	     if(strlen($efatno)>5){  // Eğer belge no döndüyse efatura numarasını ve ettn yi fiş kayıtları veri tabanına kaydediyoruz.
	         kesilmisefaturayiveritabaninakaydet($efatno,$efatid,$fisno);
	    }
	    else {
	        echo ("HATA : Fatura Numarası Dönmedi, Fatura Kesilmedi");
	    }
	} // fonk sonu
	
	   function kesilmisefaturayiveritabaninakaydet($efatno,$efatid,$fisno) {
	        $mysqli = new mysqli($GLOBALS['mysqlurl'],$GLOBALS['mysqlusername'] ,$GLOBALS['mysqlpassword'] ,$GLOBALS['mysqldatabase']);
	        $mysqli->set_charset("utf8");
	        $sql = "UPDATE faturavedekontkayitlari SET muhasebelestirildi='$efatno', efaturaettn='$efatid' where fisno=$fisno";
	      
	        if($mysqli->connect_error) {// fatura ok ama vt bağlantı hatası varsa
	            echo('HATA :Elektronik Fatura :'.$efatno.' numara ve '.$efatid.' sayılı ID ile oluşturuldu ,ancak veri tabanına kaydedilemedi. Sistem Yöneticisine Bilgi Verin
              Bağlantı Hatası Kodu : '.$mysqli->connect_errno);
	            return;
	        }
	      
	        if ($mysqli->query($sql) === FALSE) { // fatura ok ama vt kayıt hatası varsa
	            echo('HATA :Elektronik Fatura :'.$efatno.' numara ve '.$efatid.' sayılı ID ile oluşturuldu ,ancak veri tabanına kaydedilemedi. Sistem Yöneticisine Bilgi Verin
              Kayıt Hatası Kodu : '.$mysqli->errno);
	            $mysqli -> close();
	            return;
	        }
	      
	        echo ('BAŞARILI.. Elektronik Fatura :'.$efatno.' numara ve '.$efatid.' sayılı ID ile oluşturuldu ve veri tabanına kaydedildi. ');
	        $yevmiyeaciklama=$efatno.' nolu Borç Faturası';
	        $jszamandamgasi=microtime();
	        yevmiyekaydiolustur($GLOBALS['muhatapkodu'],$GLOBALS['fattarih'],'Fatura',$efatno,$yevmiyeaciklama,$GLOBALS['faturadovizi'],$GLOBALS['odenecektutar']
	            ,'Borç',$GLOBALS['tlkuru'],$GLOBALS['odenecektutar'],'0',$jszamandamgasi);
	    }// fonk sonu
	?>
