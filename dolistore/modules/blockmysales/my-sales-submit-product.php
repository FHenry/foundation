<?php

//error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);

/* SSL Management */
$useSSL = true;

require_once('config.inc.php');
include(dirname(__FILE__).'/../../config/config.inc.php');
include(dirname(__FILE__).'/../../header.php');
//include(dirname(__FILE__).'/../../init.php');
include(dirname(__FILE__).'/lib.php');


// Get env variables
$upload=0;
$id_langue_en_cours = $cookie->id_lang;
$customer_id = empty($cookie->id_customer)?'':$cookie->id_customer;
$product_id = (! empty($_GET['id_p']))?$_GET['id_p']:$_POST['id_p'];
if (! empty($_GET["id_customer"])) $customer_id=$_GET["id_customer"];
$admin=0;

// Check if current user is also an employee with admin user
$query = "SELECT id_employee, id_profile, email, active FROM "._DB_PREFIX_."employee
		WHERE lastname = '".addslashes($cookie->customer_lastname)."' and firstname = '".addslashes($cookie->customer_firstname)."'";
$subresult = Db::getInstance()->ExecuteS($query);
if (empty($subresult[0]['id_employee']))	// If not an admin user
{
	if ($customer_id != $cookie->id_customer)
	{
		print 'Error, you need to be an admin user to view other customers/suppliers.';
		die();
	}
}
else $admin=1;


$languages = Language::getLanguages();

$x = 0;
foreach ($languages AS $language) {
	$languageTAB[$x]['id_lang'] = $language['id_lang'];
	$languageTAB[$x]['name'] = $language['name'];
	$languageTAB[$x]['iso_code'] = $language['iso_code'];
	$languageTAB[$x]['img'] = '../../img/l/'.$language['id_lang'].'.jpg';

	if ($language['id_lang'] == $id_langue_en_cours)
		$iso_langue_en_cours = $language['iso_code'];

	//echo $languageTAB[$x]['id_lang']." | ".$languageTAB[$x]['name']." | ".$languageTAB[$x]['iso_code']." <br>";

	$x++;
}


/*
 * Actions
 */


//annuler le produit
if (! empty($_GET['cel']) || ! empty($_POST["cel"])) 
{
	if (! empty($_POST["product_file_path"])) unlink($_POST["product_file_path"]);
	echo "<script>window.location='../../index.php';</script>";
	exit;
}


//upload du fichier
if (! empty($_GET["up"]) || ! empty($_POST["up"])) 
{
	$error=0;

	prestalog("Upload or reupload file ".$_FILES['virtual_product_file']['tmp_name']);

	$originalfilename=$_FILES['virtual_product_file']['name'];
	if ($_FILES['virtual_product_file']['error']) {
		  switch ($_FILES['virtual_product_file']['error']){
				   case 1: // UPLOAD_ERR_INI_SIZE
				   echo "<div style='color:#FF0000'>File size is higher than server limit ! </div>";
				   break;
				   case 2: // UPLOAD_ERR_FORM_SIZE
				   echo "<div style='color:#FF0000'>File size if higher than limit in HTML form ! </div>";
				   break;
				   case 3: // UPLOAD_ERR_PARTIAL
				   echo "<div style='color:#FF0000'>File transfert was aborted ! </div>";
				   break;
				   case 4: // UPLOAD_ERR_NO_FILE
				   echo "<div style='color:#FF0000'>File name was not defined or file size is null ! </div>";
				   break;
		  }
		$upload=-1;
		$error++;
	}

	if (! $error && ! preg_match('/(\.odt|\.pdf|\.svg|\.zip|\.txt)$/i',$originalfilename))
	{
		$rulesfr.="Le nom du fichier package doit avoir une extension .odt, .pdf, .svg, .zip ou .txt<br>";
		$rulesen.="Package file name must end with extension .odt, .pdf, .svg, .zip or .txt<br>";
		echo "<div style='color:#FF0000'>".aff("Le package ne respecte pas certaines regles:<br>".$rulesfr,"Package seems to not respect some rules:<br>".$rulesen,$iso_langue_en_cours)."</div>";
		echo "<br>";
		$upload=-1;
		$error++;
	}

	if (! $error && preg_match('/(\.txt)$/i',$originalfilename) && ! preg_match('/(README)\.txt$/i',$originalfilename))
	{
		$rulesfr.="Un fichier .txt doit avoir pour nom README.txt<br>";
		$rulesen.="A .txt file must be named README.txt<br>";
		echo "<div style='color:#FF0000'>".aff("Le package ne respecte pas certaines regles:<br>".$rulesfr,"Package seems to not respect some rules:<br>".$rulesen,$iso_langue_en_cours)."</div>";
		echo "<br>";
		$upload=-1;
		$error++;
	}

	if (! $error && preg_match('/(\.zip)$/i',$originalfilename))
	{
		$rulesfr="";
		$rulesen='';
		if (! preg_match('/^module_([_a-zA-Z0-9]+)\-([0-9]+)\.([0-9\.]+)(\.zip)$/i',$originalfilename)
			&& ! preg_match('/^theme_([_a-zA-Z0-9]+)\-([0-9]+)\.([0-9\.]+)(\.zip)$/i',$originalfilename))
		{
			$rulesfr.="Le nom du fichier package doit avoir un nom du type module_monpackage-x.y(.z).zip<br>";
			$rulesfr.="Essayer de fabriquer votre package avec un outil Dolibarr officiel récent ('htdocs/build/makepack-dolibarrmodule.pl' pour les modules ou ''htdocs/build/makepack-dolibarrtheme.pl' pour les themes).";
			$rulesen.="Package file name must match module_mypackage-x.y(.z).zip<br>";
			$rulesen.="Try to build your package with a recent Dolibarr official tool ('htdocs/build/makepack-dolibarrmodule.pl' or 'htdocs/build/makepack-dolibarrtheme.pl' for themes)";
			echo "<div style='color:#FF0000'>".aff("Le package ne respecte pas certaines regles:<br>".$rulesfr,"Package seems to not respect some rules:<br>".$rulesen,$iso_langue_en_cours)."</div>";
			echo "<br>";
			$upload=-1;
			$error++;
		}
	}

	if (! $error && preg_match('/(\.zip)$/i',$originalfilename))
	{
		$zip = new ZipArchive();
		$res = $zip->open($_FILES['virtual_product_file']['tmp_name']);
		if ($res === TRUE) 
		{
			$resarray=validateZipFile($zip,$originalfilename,$_FILES['virtual_product_file']['tmp_name']);
			//$zip->close(); // already close by validateZipFile
			$error=$resarray['error'];
			$upload=$resarray['upload'];
		}
		else 
		{
			echo "<div style='color:#FF0000'>File can't be analyzed. Is it a true zip file ?<br>";
			echo "If you think this is an error, send your package by email at contact@dolibarr.org";
			echo "</div>";
			$upload=-1;
			$error++;
		}
	}

	if (! $error)
	{
		$newfilename = ProductDownload::getNewFilename(); // Return Sha1 file name
		//$newfilename = ProductDownload::getNewFilename()."_".intval($cookie->id_customer);
		$chemin_destination = _PS_DOWNLOAD_DIR_.$newfilename;

	    prestalog("Move file ".$_FILES['virtual_product_file']['tmp_name']." to ".$chemin_destination);

		if (move_uploaded_file($_FILES['virtual_product_file']['tmp_name'], $chemin_destination) != true) 
		{
			echo "<div style='color:#FF0000'>file copy impossible for the moment, please try again later </div>";
			$upload=-1;
			$error++;
			$chemin_destination='';
		}
		else
		{
			$upload=1;
		}
	}

	// If upload is a success chemin_destination is defined. It is '' otherwise.
}




//soumission du produit
if (! empty($_GET["sub"]) || (! empty($_POST["sub"]) && empty($_GET["up"]))) 
{
	$flagError = 0;
	$status = $_POST['active']; 
	if (! $admin) $status = 0;
	if (empty($status)) $status = 0;
	$product_file_name = $_POST["product_file_name"];
	$product_file_path = $_POST["product_file_path"];

	prestalog("We click on 'Submit this product' button: product_file_name=".$product_file_name." - product_file_path=".$product_file_path." - upload=".$upload);

	if ($upload < 0 || (empty($_POST["product_file_name"]) && empty($_FILES['virtual_product_file']['name'])))
	{
		$flagError = 2;
	}

	//prise des libelles
	for ($x = 0; ! empty($languageTAB[$x]); $x++ ) {

		$product_name = $resume = $description = "";
		$product_name = $_POST["product_name_l".$languageTAB[$x]['id_lang']];
		$resume = $_POST["resume_".$languageTAB[$x]['id_lang']];
		$keywords = $_POST["keywords_".$languageTAB[$x]['id_lang']];
		$description = $_POST["description_".$languageTAB[$x]['id_lang']];

		if ($languageTAB[$x]['iso_code'] == "en" && ($product_name == "" || $resume == "" || $description == "" || $keywords == "")) {
			$flagError = 1;
		} else {

			if ($languageTAB[$x]['iso_code'] != "en" && $product_name == "") {
				$product_name = $product_nameTAB[0];
			}
			if ($languageTAB[$x]['iso_code'] != "en" && $resume == "") {
				$resume = $resumeTAB[0];
			}
			if ($languageTAB[$x]['iso_code'] != "en" && $description == "") {
				$description = $descriptionTAB[0];
			}
			if ($languageTAB[$x]['iso_code'] != "en" && $keywords == "") {
				$keywords = $keywordsTAB[0];
			}
		}

		$product_nameTAB[$x] = $product_name;
		$resumeTAB[$x] = $resume;
		$keywordsTAB[$x] = $keywords;
		$descriptionTAB[$x] = $description;
	}

	//recuperation de la categorie par defaut
	$categories = Category::getSimpleCategories($cookie->id_lang);
	foreach ($categories AS $categorie) {
		if ($_POST['categories_checkbox_'.$categorie['id_category']] == 1) {
			$id_categorie_default = $categorie['id_category'];
			break;
		}
	}
	if ($id_categorie_default == "") $flagError = 3;


	//si pas derreur de saisis, traitement en base
	if ($flagError == 0) 
	{
		$taxe_rate = $_POST['rate_tax'];
		$taxe_id = $_POST["id_tax"];
		if (empty($taxe_id)) $taxe_id = 0;

		// Define prices
		$prix_ht = $_POST["price"];
		$prix_ttc = round($prix_ht * (100 + (float) $taxe_rate) / 100, 2);

		//prise des date
		$dateToday = date ("Y-m-d");
		$dateNow = date ("Y-m-d H:i:s");
		$dateRef = date ("YmdHis");

		//reference du produit
		$reference = 'c'.$cookie->id_customer.'d'.$dateRef;

		$qty=1000;
		if ($prix_ttc == 0) $qty=0;

		//insertion du produit en base
		$query = 'INSERT INTO '._DB_PREFIX_.'product (
		`id_supplier`, `id_manufacturer`, `id_tax`, `id_category_default`, `id_color_default`, `on_sale`, `ean13`, `ecotax`, `quantity`, `price`, `wholesale_price`, `reduction_price`, `reduction_percent`, `reduction_from`, `reduction_to`, `reference`, `supplier_reference`, `location`, `weight`, `out_of_stock`, `quantity_discount`, `customizable`, `uploadable_files`, `text_fields`, `active`, `indexed`, `date_add`, `date_upd`) VALUES (
		            0,                 0, '.$taxe_id.', '.$id_categorie_default.',          0,          0,   \'\',     0.00,   '.$qty.', '.$prix_ht.', '.$prix_ht.',             0.00,                   0, \''.$dateToday.'\', \''.$dateToday.'\', \''.$reference.'\',     \'\',       \'\',        0,              0,                   0,              0,                  0,             0, '.$status.',      1, \''.$dateNow.'\', \''.$dateNow.'\'
		)';

		$result = Db::getInstance()->ExecuteS($query);
		if ($result === false) die(Tools::displayError('Invalid loadLanguage() SQL query!: '.$query));


		// Get product id
		$query = 'SELECT id_product FROM '._DB_PREFIX_.'product
		WHERE reference = \''.$reference.'\'
		AND date_add = \''.$dateNow.'\' ';
		$result = Db::getInstance()->ExecuteS($query);
		if ($result === false) die(Tools::displayError('Invalid loadLanguage() SQL query!: '.$query));
		foreach ($result AS $row)
		{
		    $id_product = $row['id_product'];
		}

		// Add language description of product
		for ($x = 0; $product_nameTAB[$x]; $x++)
		{
			$languageTAB[$x]['id_lang'];
			$languageTAB[$x]['name'];

			$link_rewrite = preg_replace('/[^a-zA-Z0-9-]/','-', $product_nameTAB[$x]);

			$query = 'INSERT INTO `'._DB_PREFIX_.'product_lang` (`id_product`, `id_lang`, `description`, `description_short`, `link_rewrite`, `meta_description`, `meta_keywords`, `meta_title`, `name`, `available_now`, `available_later`)
			 VALUES ('.$id_product.", ".$languageTAB[$x]['id_lang'].", '".addslashes($descriptionTAB[$x])."', '".addslashes($resumeTAB[$x])."', '".addslashes($link_rewrite)."', '".addslashes($product_nameTAB[$x])."', '".addslashes($keywordsTAB[$x])."', '".addslashes($product_nameTAB[$x])."', '".addslashes($product_nameTAB[$x])."', '".aff("Disponible", "Available", $iso_langue_en_cours)."', '".aff("En fabrication", "In build", $iso_langue_en_cours)."')";
			$result = Db::getInstance()->ExecuteS($query);
			if ($result === false) die(Tools::displayError('Invalid loadLanguage() SQL query! : '.$query));
		}

		// Add tag description of product
		for ($x = 0; $product_nameTAB[$x]; $x++)
		{
			$id_lang=$languageTAB[$x]['id_lang'];
			$tags=preg_split('/[\s,]+/',$keywordsTAB[$x]);
			foreach($tags as $tag)
			{
				$id_tag=0;

				// Search existing tag
				$query = 'SELECT id_tag FROM '._DB_PREFIX_.'tag
				WHERE id_lang = \''.$id_lang.'\'
				AND name = \''.addslashes($tag).'\' ';
				$result = Db::getInstance()->ExecuteS($query);
				if ($result === false) die(Tools::displayError('Invalid loadLanguage() SQL query!: '.$query));
				foreach ($result AS $row)
				{
					$id_tag = $row['id_tag'];
					prestalog("tag id for id_lang ".$id_lang.", name ".$tag." is ".$id_tag);
				}

				if (empty($id_tag))
				{
					$query = "INSERT INTO "._DB_PREFIX_."tag(id_lang, name) VALUES ('".$id_lang."', '".addslashes($tag)."')";
					$result = Db::getInstance()->ExecuteS($query);
					//if ($result === false) die(Tools::displayError('Invalid loadLanguage() SQL query! : '.$query));

					$id_tag = Db::getInstance()->Insert_ID();
					prestalog("We created tag for id_lang ".$id_lang.", name ".$tag.", id is ".$id_tag);
				}

				if (! empty($id_tag) && $id_tag > 0)
				{
					// Add tag link
					$query = "INSERT INTO "._DB_PREFIX_."product_tag(id_product, id_tag) VALUES ('".$id_product."', '".$id_tag."')";
					$result = Db::getInstance()->ExecuteS($query);

					prestalog("We insert link id_product ".$id_product.", id_tag ".$id_tag);
				}
			}
		}

		//mise en base du lien avec le produit telechargeable
		$product_file_newname = basename($product_file_path);

		$query = 'INSERT INTO `'._DB_PREFIX_.'product_download` (`id_product`, `display_filename`, `physically_filename`, `date_deposit`, `date_expiration`, `nb_days_accessible`, `nb_downloadable`, `active`) VALUES (
		'.$id_product.', "'.$product_file_name.'", "'.$product_file_newname.'", "'.$dateNow.'", "0000-00-00 00:00:00", 3650, 0, 1
		)';
		$result = Db::getInstance()->ExecuteS($query);
		if ($result === false) die(Tools::displayError('Invalid loadLanguage() SQL query! : '.$query));


		//mise en piece jointe du fichier
		if ($prix_ttc == 0)
		{
			//mise dans la base des fichiers joints
			$query = 'INSERT INTO `'._DB_PREFIX_.'attachment` (`file`, `mime`) VALUES ("'.$product_file_newname.'", "text/plain");';
			$result = Db::getInstance()->ExecuteS($query);

			//recuperation de l'id du fichier joint
			$query = 'SELECT `id_attachment` FROM `'._DB_PREFIX_.'attachment`
			WHERE `file` = "'.$product_file_newname.'"';
			$result = Db::getInstance()->ExecuteS($query);
			if ($result === false) die(Tools::displayError('Invalid loadLanguage() SQL query!: '.$query));
			foreach ($result AS $row)
				$id_attachment = $row['id_attachment'];

			//set des nom du fichier en toute les langues
			for ($x = 0; $languageTAB[$x]; $x++ ) {
				$id_lang = $languageTAB[$x]['id_lang'];
				$query = 'INSERT INTO `'._DB_PREFIX_.'attachment_lang` (`id_attachment`, `id_lang`, `name`, `description`) VALUES ('.$id_attachment.', '.$id_lang.', "'.$product_file_name.'", "")';
				$result = Db::getInstance()->ExecuteS($query);
			}

			//cree lien fichier vers fichiers joint
			$query = 'INSERT INTO `'._DB_PREFIX_.'product_attachment` (`id_product`, `id_attachment`) VALUES ('.$id_product.', '.$id_attachment.')';
			$result = Db::getInstance()->ExecuteS($query);
		}
		else
		{
			prestalog("price is ".$prix_ttc.", so not null, so we do not add file to download tabs");
		}

		//inscritption du produit dans ttes les categories choisis
		$categories = Category::getSimpleCategories($cookie->id_lang);
	    foreach ($categories AS $categorie) {

			if ($_POST['categories_checkbox_'.$categorie['id_category']] == 1) {
				$query = 'INSERT INTO `'._DB_PREFIX_.'category_product` (`id_category`, `id_product`, `position`) VALUES
						('.$categorie['id_category'].', '.$id_product.', 1);';
				$result = Db::getInstance()->ExecuteS($query);
				if ($result === false) die(Tools::displayError('Invalid loadLanguage() SQL query! : '.$query));
			}
		}

		// Redirect to next page
		echo "<script>window.location='./my-sales-images-product.php?id_p=$id_product';</script>";
	}

	if ($flagError == 1) {
		echo "<div style='color:#FF0000'>";echo aff("Tous les champs Anglais sont obligatoires.", "All English fields are required.", $iso_langue_en_cours); echo " </div><br>";
	}

	if ($flagError == 2) {
		echo "<div style='color:#FF0000'>";echo aff("Vous devez uploader un produit", "You have to upload a product", $iso_langue_en_cours); echo " </div><br>";
	}

	if ($flagError == 3) {
		echo "<div style='color:#FF0000'>";echo aff("Vous devez choisir une categorie", "You have to choose a category", $iso_langue_en_cours); echo " </div><br>";
	}

}




/*
 * View
 */

$tmpname=((! empty($_POST["product_file_name"])) ? $_POST["product_file_name"] : ((! empty($_FILES['virtual_product_file']['name'])) ? $_FILES['virtual_product_file']['name'] : "" ));
$tmppath=((! empty($_POST["product_file_path"])) ? $_POST["product_file_path"] : ((! empty($chemin_destination)) ? $chemin_destination : ""));


echo aff("<h2>Soumettre un module/produit</h2>", "<h2>Submit a module/plugin</h2>", $iso_langue_en_cours);
?>
<br />

<?php

print '<input type="checkbox" required="required" name="agreewithtermofuse"> ';
echo aff('J\'ai lu et suis d\'accord avec les conditions d\'utilisations disponible sur <a href="http://www.dolistore.com/lang-fr/content/3-conditions-generales-de-ventes" target="_blank">http://www.dolistore.com/lang-fr/content/3-conditions-generales-de-ventes</a>',
		 'I\'ve read and I agree with terms and conditions of use available on page <a href="http://www.dolistore.com/content/3-terms-and-conditions-of-use" target="_blank">http://www.dolistore.com/content/3-terms-and-conditions-of-use</a>', $iso_langue_en_cours);
print '<br>';
print '<br>';

echo '

<script type="text/javascript" src="'.__PS_BASE_URI__.'js/tinymce/jscripts/tiny_mce/jquery.tinymce.js"></script>
			<script type="text/javascript">
			function tinyMCEInit(element)
			{
				$().ready(function() {
					$(element).tinymce({
						// Location of TinyMCE script
						script_url : \''.__PS_BASE_URI__.'js/tinymce/jscripts/tiny_mce/tiny_mce.js\',
						// General options
						theme : "advanced",
						plugins : "safari,style,layer,table,advlink,inlinepopups,media,contextmenu,paste,directionality,fullscreen",

						// Theme options
						theme_advanced_buttons1 : "fullscreen,code,|,bold,italic,underline,strikethrough,|,justifyleft,justifycenter,justifyright,|,bullist,numlist,|,link,unlink,|,forecolor",
						theme_advanced_buttons2 : "",
						theme_advanced_buttons3 : "",

						theme_advanced_toolbar_location : "top",
						theme_advanced_toolbar_align : "left",
						width : "100%",
						theme_advanced_resizing : true,
						content_css : "'.__PS_BASE_URI__.'themes/'._THEME_NAME_.'/css/global.css",
						// Drop lists for link/image/media/template dialogs
						//template_external_list_url : "lists/template_list.js",
						external_link_list_url : "lists/link_list.js",
						external_image_list_url : "lists/image_list.js",
						//media_external_list_url : "lists/media_list.js",
						elements : "nourlconvert",
						convert_urls : false,
						language : "'.(file_exists(_PS_ROOT_DIR_.'/js/tinymce/jscripts/tiny_mce/langs/'.$iso_langue_en_cours.'.js') ? $iso_langue_en_cours : 'en').'"
					});
				});
			}
			tinyMCEInit(\'textarea.rte\');
			</script>


			<script language="javascript">
				function maxlength(text,length) {
					if(text.innerText.length>length)
						text.innerText=text.innerText.substr(0,length);
				}
			</script>

';

?>



<FORM name="fmysalessubprod" method="POST" ENCTYPE="multipart/form-data" class="formsubmit" action="my-sales-submit-product.php">

<table width="100%" border="0" style="padding-bottom: 5px;">

  <tr>
    <td colspan="2"><hr></td>
  </tr>

  <tr>
    <td nowrap="nowrap" valign="top"><?php echo aff("Nom du module/produit", "Module/product name : ", $iso_langue_en_cours); ?> </td>
    <td>
    	<?php for ($x = 0; ! empty($languageTAB[$x]); $x++ ) { ?>
        	<input class="inputlarge" name="product_name_l<?php echo $languageTAB[$x]['id_lang']; ?>" type="text" size="26" maxlength="100" value="<?php echo empty($_POST["product_name_l".$languageTAB[$x]['id_lang']])?'':$_POST["product_name_l".$languageTAB[$x]['id_lang']]; ?>" />
            <img src="<?php echo $languageTAB[$x]['img']; ?>" alt="<?php echo $languageTAB[$x]['iso_code']; ?>">
			<?php echo $languageTAB[$x]['iso_code'];
			if ($languageTAB[$x]['iso_code'] == 'en') echo ', '.aff("obligatoire","mandatory",$iso_langue_en_cours);
			else echo ', '.aff("optionnel","optionnal",$iso_langue_en_cours);
			?>
			<br />
        <?php } ?>
    </td>
  </tr>

  <tr>
    <td colspan="2"><hr></td>
  </tr>

  <tr>
    <td valign="top">Status : </td>
    <td>
    <!--
    <input name="active" id="active_on" value="1" <?php if ($_POST['active'] == 1 || $_POST['active'] != "") echo 'checked'; ?>  disabled type="radio" style="border:none">
    -->
    <input name="active" id="active_on" value="1" type="radio" style="border:none" disabled />
    <img src="../../img/os/2.gif" alt="Enabled" title="Enabled" style="padding: 0px 5px;"> <?php echo aff("Actif", "Enabled", $iso_langue_en_cours); ?>
    <br />
    <!--
	<input name="active" id="active_off" value="0" <?php if ($_POST['active'] == 0 && $_POST['active'] == "") echo 'checked'; ?> type="radio" style="border:none" />
    -->
    <input name="active" id="active_off" value="0" type="radio" style="border:none" checked />
    <img src="../../img/os/6.gif" alt="Disabled" title="Disabled" style="padding: 0px 5px;"> <?php echo aff("Inactif (la soumission sera activé une fois validée par les modérateurs, ceci prend 2 à 10 jours)", "Disabled (submission will be enabled once validated by moderators, this takes 2 to 10 days)", $iso_langue_en_cours); ?>
    </td>
  </tr>


  <tr>
    <td colspan="2"><hr></td>
  </tr>


  <tr>
    <td nowrap="nowrap" valign="top"><?php echo aff("Package à diffuser<br>(fichier .zip pour<br>un module ou theme)", "Package to distribute<br>(.zip file for<br> a module or theme)", $iso_langue_en_cours); ?></td>
    <td>
        <?php
		if ($upload >= 0 && (! empty($_POST["product_file_name"]) || ! empty($_FILES['virtual_product_file']['name']))) 
		{
			if ($_POST["product_file_name"] != "") $file_name = $_POST["product_file_name"];
			if ($_FILES['virtual_product_file']['name'] != "") $file_name = $_FILES['virtual_product_file']['name'];
			echo aff("Fichier ".$file_name." prêt.","File ".$file_name." ready.",$iso_langue_en_cours);

		}
		else 
		{
		?>
			<?php echo aff("Taille maximal du fichier: ".ini_get('upload_max_filesize'),"Maximum file size is: ".ini_get('upload_max_filesize'), $iso_langue_en_cours); ?>
            <br />
	        <input id="virtual_product_file" name="virtual_product_file" value="" class="" onchange="javascript:
    																					document.fmysalessubprod.action='?up=1';
                                                                                        document.fmysalessubprod.submit();" maxlength="10000000" type="file">
        	<?php
		}

		?>
		<br>
		<input type="hidden" name="product_file_name" id="product_file_name" value="<?php echo $tmpname; ?>" >
		<input type="hidden" name="product_file_path" id="product_file_path" value="<?php echo $tmppath; ?>" >
    </td>
  </tr>


  <tr>
    <td colspan="2"><hr></td>
  </tr>

  <tr>
    <td nowrap="nowrap" valign="top"><?php echo aff("Prix de vente HT : ", "Sale price (excl tax) : ", $iso_langue_en_cours); ?></td>
    <td>
        <input required="required" size="7" maxlength="7" name="price" id="price" value="<?php if (! empty($_POST["price"])) echo round($_POST["price"],5); else print '0'; ?>" onkeyup="javascript:this.value = this.value.replace(/,/g, '.');" type="text">
		<?php print aff(' Euros &nbsp; ("0" si "gratuit")',' Euros &nbsp; ("0" means "free")', $iso_langue_en_cours); ?>

    	<?php
		$taxVal = 19.6;
		$taxes = Tax::getTaxes($cookie->id_lang);

		foreach ($taxes AS $taxe) 
		{
			if ($taxe['rate'] != $taxVal) continue;
			echo '<input type="hidden" name="id_tax" id="id_tax" value="'.$taxe['id_tax'].'">';
			echo '<input type="hidden" name="rate_tax" id="rate_tax" value="'.$taxe['rate'].'">';
			print '<br>';
			print aff("According to foundation status, a vat rate of ".$taxVal." will be added to this price, if price is not null. Your ".$commissioncee."% part is calculated onto the price excluding tax.", "Compte tenu du status de l'association Dolibarr, une taxe de ".$taxVal." sera ajoutée à ce montant pour déterminer le prix final (si ce montant n'est pas nul). Votre part de ".$commissioncee."% est calculée sur le montant sans cette taxe.", $iso_langue_en_cours);
		}
		?>
	</td>
  </tr>


  <tr>
    <td colspan="2"><hr></td>
  </tr>


	<!-- Categories -->
  <tr>
    <td width="14%" valign="top">
    <?php echo aff("Cocher toutes les categories dans lesquelles le produit apparaitra : ", "Check all categories in which product will appear : ", $iso_langue_en_cours); ?>
 	</td>
    <td width="86%">
		<?php

		echo '<table width="100%" border="0" cellspacing="5" cellpadding="0">';

        $categories = Category::getSimpleCategories($cookie->id_lang);
        $x = 0;
        foreach ($categories AS $categorie) {
			/*if (in_array($categorie['id_category'],array(1,2,4))) 
			{
				echo '<tr bgcolor="'.$bgcolor.'"><td nowrap="nowrap" valign="top" align="left">';
				echo $categorie['name'];		
				echo '</td></tr>';
				continue; 	// We discard some categories
			}*/

			$query = 'SELECT id_category, active, level_depth, id_parent FROM `'._DB_PREFIX_.'category` WHERE `id_category` = \''.$categorie['id_category'].'\'';
			$result = Db::getInstance()->ExecuteS($query);
			if ($result === false) die(Tools::displayError('Invalid loadLanguage() SQL query!: '.$query));

			$level = 0; $active = 0;
			foreach ($result AS $row)
			{
				$active = $row['active'];
				$level = $row['level_depth'];
			}

			if ($categorie['id_category'] > 1 && $active == 1) {

				if ($x%2)
				 $bgcolor="#FFF4EA";
				else
				 $bgcolor="#FFDBB7";

				echo '<tr bgcolor="'.$bgcolor.'"><td nowrap="nowrap" valign="top" align="left">';
				//echo str_repeat('&nbsp;', $level);
				echo '<input name="categories_checkbox_'.$categorie['id_category'].'" type="checkbox" value="1" ';

				if (! empty($_POST['categories_checkbox_'.$categorie['id_category']]) && $_POST['categories_checkbox_'.$categorie['id_category']] == 1) echo " checked ";

				echo ' />'.$categorie['name'];
				echo '</td></tr>';
				$x++;
			}
        }
		echo '</table>';
        ?>
       </td>
  </tr>


  <tr>
    <td colspan="2"><hr></td>
  </tr>

  <tr>
    <td nowrap="nowrap" valign="top"><?php echo aff("Mots cl&eacute;s : ", "Keywords : ", $iso_langue_en_cours); ?></td>
    <td nowrap="nowrap">
        <?php for ($x = 0; ! empty($languageTAB[$x]); $x++ ) { ?>
        	<input class="inputlarge" name="keywords_<?php echo $languageTAB[$x]['id_lang']; ?>" type="text" size="26" maxlength="100" value="<?php echo empty($_POST["keywords_".$languageTAB[$x]['id_lang']])?'':$_POST["keywords_".$languageTAB[$x]['id_lang']]; ?>" />
            <img src="<?php echo $languageTAB[$x]['img']; ?>" alt="<?php echo $languageTAB[$x]['iso_code']; ?>">
			<?php
			echo $languageTAB[$x]['iso_code'];
			if ($languageTAB[$x]['iso_code'] == 'en') echo ', '.aff("obligatoire","mandatory",$iso_langue_en_cours);
			else echo ', '.aff("optionnel","optionnal",$iso_langue_en_cours);
			?>
			<br>
        <?php } ?>
    </td>
  </tr>


  <tr>
    <td colspan="2"><hr></td>
  </tr>

  <?php for ($x = 0; ! empty($languageTAB[$x]); $x++ ) { ?>
  <tr>
    <td colspan="2" nowrap="nowrap" valign="top"><?php echo aff("R&eacute;sum&eacute ", "Short description ", $iso_langue_en_cours); ?>
	(<img src="<?php echo $languageTAB[$x]['img']; ?>" alt="<?php echo $languageTAB[$x]['iso_code']; ?>">
		<?php echo $languageTAB[$x]['iso_code'];
			if ($languageTAB[$x]['iso_code'] == 'en') echo ', '.aff("obligatoire","mandatory",$iso_langue_en_cours);
			else echo ', '.aff("optionnel","optionnal",$iso_langue_en_cours);
			?>):
    <input type="text" id="resumeLength_<?php echo $languageTAB[$x]['id_lang']; ?>" value="400" size="2" width="3" style="border:0; font-size:10px; color:#333333;"> <?php echo aff("caractères restant", "characters left", $iso_langue_en_cours); ?>.
	</td>
  </tr>
  <tr>
    <td colspan="2" nowrap="nowrap">
        	<textarea id="resume_<?php echo $languageTAB[$x]['id_lang']; ?>" name="resume_<?php echo $languageTAB[$x]['id_lang']; ?>"
            onkeyup="javascript:resumeLength_<?php echo $languageTAB[$x]['id_lang']; ?>.value=parseInt(400-this.value.length); if(this.value.length>=400)this.value=this.value.substr(0,399);"
            onkeydown="javascript:resumeLength_<?php echo $languageTAB[$x]['id_lang']; ?>.value=parseInt(400-this.value.length); if(this.value.length>=400)this.value=this.value.substr(0,399);"
            onchange="javascript:resumeLength_<?php echo $languageTAB[$x]['id_lang']; ?>.value=parseInt(400-this.value.length); if(this.value.length>=400)this.value=this.value.substr(0,399);"
            cols="60" rows="3"><?php echo empty($_POST["resume_".$languageTAB[$x]['id_lang']])?'':$_POST["resume_".$languageTAB[$x]['id_lang']]; ?></textarea>
    </td>
  </tr>
  <?php } ?>

  <tr>
    <td colspan="2"><hr></td>
  </tr>

  <?php for ($x = 0; ! empty($languageTAB[$x]); $x++ ) { ?>
    <tr>
        <td colspan="2"><br>
        	<?php echo aff("Description large ", "Large description ", $iso_langue_en_cours); ?>
            (<img src="<?php echo $languageTAB[$x]['img']; ?>" alt="<?php echo $languageTAB[$x]['iso_code']; ?>">
			<?php echo $languageTAB[$x]['iso_code'];
			if ($languageTAB[$x]['iso_code'] == 'en') echo ', '.aff("obligatoire","mandatory",$iso_langue_en_cours);
			else echo ', '.aff("optionnel","optionnal",$iso_langue_en_cours);
			?>):
        </td>
    </tr>
    <tr>
        <td colspan="2">
            <textarea class="rte" cols="100" rows="10"
			  id="description_<?php echo $languageTAB[$x]['id_lang']; ?>"
			name="description_<?php echo $languageTAB[$x]['id_lang']; ?>">
			<?php

			$publisher=trim($cookie->customer_firstname.' '.$cookie->customer_lastname);
			$defaulten='
Module version: <strong>1.0</strong><br>
Publisher/Licence: <strong>'.$publisher.'</strong> / <strong>AGPL</strong><br>
User interface language: <strong>English</strong><br>
Help/Support: <strong>None / <strike>Forum www.dolibarr.org</strike> / <strike>Mail to contact@publisher.com</strike></strong><br>
</ul>
Prerequisites:<br>
<ul>
<li> Dolibarr min version: <strong>'.$minversion.'</strong> </li>
<li> Dolibarr max version: <strong>'.$maxversion.'</strong> </li>
</ul>
<p>Install:</p>
<ul>
<li> Download the archive file of module (.zip file) from web site <a title="http://www.dolistore.com" rel="nofollow" href="http://www.dolistore.com/" target="_blank">DoliStore.com</a> </li>
<li> Put the file into the root directory of Dolibarr. </li>
<li> Uncompress the zip file, for example with command </li>
<div style="text-align: left;" dir="ltr">
<div style="font-family: monospace;">
<pre><span>unzip</span> '.(! empty($file_name)?$file_name:'modulefile.zip').'</pre>
</div>
</div>
<li> Module or skin is then available and can be activated. </li>
</ul>';
			$defaultfr='
Module version: <strong>1.0</strong><br>
Editeur/Licence: <strong>'.$publisher.'</strong> / <strong>AGPL</strong><br>
Langage interface: <strong>Anglais</strong><br>
Assistance: <strong>Aucune / <strike>Forum www.dolibarr.org</strike> / <strike>Par mail à contact@editeur.com</strike></strong><br>
Pr&eacute;requis: <br>
<ul>
<li> Dolibarr min version: <strong>'.$minversion.'</strong> </li>
<li> Dolibarr max version: <strong>'.$maxversion.'</strong> </li>
</ul>
Installation:<br>
<ul>
<li> T&eacute;l&eacute;charger le fichier archive du module (.zip) depuis le site  web <a title="http://www.dolistore.com" rel="nofollow" href="http://www.dolistore.com/" target="_blank">DoliStore.com</a> </li>
<li> Placer le fichier dans le r&eacute;pertoire racine de dolibarr. </li>
<li> Decompressez le fichier zip, par exemple par la commande </li>
<div style="text-align: left;" dir="ltr">
<div style="font-family: monospace;">
<pre><span>unzip</span> '.(! empty($file_name)?$file_name:'fichiermodule.zip').'</pre>
</div>
</div>
<li> Le module ou thème est alors disponible et activable. </li>
</ul>';
			$defaultes='
Versión del Módulo: <strong>1.0</strong><br>
Creador/Licencia:  <strong>'.$publisher.'</strong> / <strong>AGPL</strong><br>
Idioma interfaz usuario: <strong>Inglés</strong><br>
Ayuda/Soporte: <strong>No / <strike>foro www.dolibarr.org</strike> / <strike>mail a contacto@creador.com</strike></strong><br>
Prerrequisitos: <br>
<ul>   
<li> Versión min Dolibarr: <strong>'.$minversion.'</strong></li>
<li> Versión max Dolibarr: <strong>'.$maxversion.'</strong></li>
</ul>
Para instalar este módulo:<br>
<ul>
<li> Descargar el archivo del módulo (archivo .zip) desde la web <a title="http://www.dolistore.com" rel="nofollow" href="http://www.dolistore.com/" target="_blank">DoliStore.com</a> </li>
<li> Ponga el archivo en el directorio raíz de Dolibarr.</li>
<li> Descomprima el zip archivo, por ejamplo usando el comando</li>
<div style="text-align: left;" dir="ltr">
<div style="font-family: monospace;">
<pre><span>unzip</span> '.(! empty($file_name)?$file_name:'fichiermodule.zip').'</pre>
</div>
</div>
<li> El módulo o tema está listo para ser activado.</li>
</ul>';

			if (empty($_POST["description_".$languageTAB[$x]['id_lang']]))
			{
				if ($languageTAB[$x]['iso_code'] == 'fr') print $defaultfr;
				else if ($languageTAB[$x]['iso_code'] == 'es') print $defaultes;
				else print $defaulten;
			}
			else echo $_POST["description_".$languageTAB[$x]['id_lang']];

			?>
            </textarea>
        </td>
    </tr>
   <?php } ?>
  <tr>
    <td colspan="2"><br></td>
  </tr>

  <tr>
	    <td colspan="2" nowrap="nowrap" align="center">
		<?php // print 'xxxy: '.$tmpname.' - '.$tmppath; ?>
		<input class="button_large" name="sub" type="submit" <?php if (empty($tmpname) || empty($tmppath)) print 'disabled="disabled"'; ?> value="<?php print aff(" Valider ce produit ", " Submit this product ", $iso_langue_en_cours); ?>">
		&nbsp; &nbsp; &nbsp;
		<input class="button_large" name="cel" type="submit" value="<?php print aff(" Annuler ", " Cancel ", $iso_langue_en_cours); ?>">
	</td>
  </tr>

</table>




</FORM>
<?php
include(dirname(__FILE__).'/../../footer.php');
?>
