<?php
/* Copyright (C) 2002-2004 Rodolphe Quiedeville  <rodolphe@quiedeville.org>
 * Copyright (C) 2004      Eric Seigne           <eric.seigne@ryxeo.com>
 * Copyright (C) 2004-2011 Laurent Destailleur   <eldy@users.sourceforge.net>
 * Copyright (C) 2005      Marc Barilley / Ocebo <marc@ocebo.com>
 * Copyright (C) 2005-2011 Regis Houssin         <regis@dolibarr.fr>
 * Copyright (C) 2006      Andre Cianfarani      <acianfa@free.fr>
 * Copyright (C) 2008      Raphael Bertrand (Resultic)   <raphael.bertrand@resultic.fr>
 * Copyright (C) 2010-2011 Juanjo Menent         <jmenent@2byte.es>
 * Copyright (C) 2010-2011 Philippe Grand        <philippe.grand@atoo-net.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *	\file       htdocs/comm/propal/class/propal.class.php
 *	\brief      Fichier de la classe des propales
 *	\author     Rodolphe Qiedeville
 *	\author	    Eric Seigne
 *	\author	    Laurent Destailleur
 *	\version    $Id: propal.class.php,v 1.111 2011/08/08 01:53:26 eldy Exp $
 */

require_once(DOL_DOCUMENT_ROOT ."/core/class/commonobject.class.php");
require_once(DOL_DOCUMENT_ROOT ."/product/class/product.class.php");
require_once(DOL_DOCUMENT_ROOT ."/contact/class/contact.class.php");


/**
 *	\class      Propal
 *	\brief      Classe permettant la gestion des propales
 */
class Propal extends CommonObject
{
	var $db;
	var $error;
	var $element='propal';
	var $table_element='propal';
	var $table_element_line='propaldet';
	var $fk_element='fk_propal';
	var $ismultientitymanaged = 1;	// 0=No test on entity, 1=Test with field entity, 2=Test with link by societe

	var $id;

	var $socid;		// Id client
	var $client;		// Objet societe client (a charger par fetch_client)

	var $contactid;
	var $fk_project;
	var $author;
	var $ref;
	var $ref_client;
	var $statut;					// 0 (draft), 1 (validated), 2 (signed), 3 (not signed), 4 (billed)
	var $datec;						// Date of creation
	var $datev;						// Date of validation
	var $date;						// Date of proposal
	var $datep;						// Same than date
	var $date_livraison;
	var $fin_validite;

	var $user_author_id;
	var $user_valid_id;
	var $user_close_id;

	var $total_ht;					// Total net of tax
	var $total_tva;					// Total VAT
	var $total_localtax1;			// Total Local Taxes 1
	var $total_localtax2;			// Total Local Taxes 2
	var $total_ttc;					// Total with tax
	var $price;						// deprecated (for compatibility)
	var $tva;						// deprecated (for compatibility)
	var $total;						// deprecated (for compatibility)

	var $cond_reglement_id;
	var $cond_reglement_code;
	var $mode_reglement_id;
	var $mode_reglement_code;
	var $remise;
	var $remise_percent;
	var $remise_absolue;
	var $note;
	var $note_public;
	var $fk_delivery_address;		// deprecated (for compatibility)
	var $fk_address;
	var $address_type;
	var $adresse;
	var $availability_id;
	var $availability_code;
	var $demand_reason_id;
	var $demand_reason_code;

	var $products=array();

	var $lines = array();
	var $line;

	var $origin;
	var $origin_id;

	var $labelstatut=array();
	var $labelstatut_short=array();

	// Pour board
	var $nbtodo;
	var $nbtodolate;

	var $specimen;


	/**
	 *		\brief      Constructeur
	 *      \param      DB          Database handler
	 *      \param      socid		Id third party
	 *      \param      propalid    Id proposal
	 */
	function Propal($DB, $socid="", $propalid=0)
	{
		global $conf,$langs;

		$this->db = $DB ;
		$this->socid = $socid;
		$this->id = $propalid;
		$this->products = array();
		$this->remise = 0;
		$this->remise_percent = 0;
		$this->remise_absolue = 0;

		$this->duree_validite=$conf->global->PROPALE_VALIDITY_DURATION;

		$langs->load("propal");
		$this->labelstatut[0]=($conf->global->PROPAL_STATUS_DRAFT_LABEL ? $conf->global->PROPAL_STATUS_DRAFT_LABEL : $langs->trans("PropalStatusDraft"));
		$this->labelstatut[1]=($conf->global->PROPAL_STATUS_VALIDATED_LABEL ? $conf->global->PROPAL_STATUS_VALIDATED_LABEL : $langs->trans("PropalStatusValidated"));
		$this->labelstatut[2]=($conf->global->PROPAL_STATUS_SIGNED_LABEL ? $conf->global->PROPAL_STATUS_SIGNED_LABEL : $langs->trans("PropalStatusSigned"));
		$this->labelstatut[3]=($conf->global->PROPAL_STATUS_NOTSIGNED_LABEL ? $conf->global->PROPAL_STATUS_NOTSIGNED_LABEL : $langs->trans("PropalStatusNotSigned"));
		$this->labelstatut[4]=($conf->global->PROPAL_STATUS_BILLED_LABEL ? $conf->global->PROPAL_STATUS_BILLED_LABEL : $langs->trans("PropalStatusBilled"));
		$this->labelstatut_short[0]=($conf->global->PROPAL_STATUS_DRAFTSHORT_LABEL ? $conf->global->PROPAL_STATUS_DRAFTSHORT_LABEL : $langs->trans("PropalStatusDraftShort"));
		$this->labelstatut_short[1]=($conf->global->PROPAL_STATUS_VALIDATEDSHORT_LABEL ? $conf->global->PROPAL_STATUS_VALIDATEDSHORT_LABEL : $langs->trans("Opened"));
		$this->labelstatut_short[2]=($conf->global->PROPAL_STATUS_SIGNEDSHORT_LABEL ? $conf->global->PROPAL_STATUS_SIGNEDSHORT_LABEL : $langs->trans("PropalStatusSignedShort"));
		$this->labelstatut_short[3]=($conf->global->PROPAL_STATUS_NOTSIGNEDSHORT_LABEL ? $conf->global->PROPAL_STATUS_NOTSIGNEDSHORT_LABEL : $langs->trans("PropalStatusNotSignedShort"));
		$this->labelstatut_short[4]=($conf->global->PROPAL_STATUS_BILLEDSHORT_LABEL ? $conf->global->PROPAL_STATUS_BILLEDSHORT_LABEL : $langs->trans("PropalStatusBilledShort"));
	}


	/**
	 * 	Add line into array products
	 *	$this->client doit etre charge
	 * 	@param     	idproduct       	Id du produit a ajouter
	 * 	@param     	qty             	Quantity
	 * 	@param      remise_percent  	Remise relative effectuee sur le produit
	 *	TODO	Remplacer les appels a cette fonction par generation objet Ligne
	 *			insere dans tableau $this->products
	 */
	function add_product($idproduct, $qty, $remise_percent=0)
	{
		global $conf, $mysoc;

		if (! $qty) $qty = 1;

		dol_syslog("Propal::add_product $idproduct, $qty, $remise_percent");
		if ($idproduct > 0)
		{
			$prod=new Product($this->db);
			$prod->fetch($idproduct);

			$productdesc = $prod->description;

			$tva_tx = get_default_tva($mysoc,$this->client,$prod->id);
			// local taxes
			$localtax1_tx = get_default_localtax($mysoc,$this->client,1,$prod->tva_tx);
			$localtax2_tx = get_default_localtax($mysoc,$this->client,2,$prod->tva_tx);

			// multiprix
			if($conf->global->PRODUIT_MULTIPRICES && $this->client->price_level)
			{
				$price = $prod->multiprices[$this->client->price_level];
			}
			else
			{
				$price = $prod->price;
			}

			$line = new PropaleLigne($this->db);

			$line->fk_product=$idproduct;
			$line->desc=$productdesc;
			$line->qty=$qty;
			$line->subprice=$price;
			$line->remise_percent=$remise_percent;
			$line->tva_tx=$tva_tx;

			$this->products[]=$line;
		}
	}

	/**
	 *    \brief     Ajout d'une ligne remise fixe dans la proposition, en base
	 *    \param     idremise			Id de la remise fixe
	 *    \return    int          		>0 si ok, <0 si ko
	 */
	function insert_discount($idremise)
	{
		global $langs;

		include_once(DOL_DOCUMENT_ROOT.'/lib/price.lib.php');
		include_once(DOL_DOCUMENT_ROOT.'/core/class/discount.class.php');

		$this->db->begin();

		$remise=new DiscountAbsolute($this->db);
		$result=$remise->fetch($idremise);

		if ($result > 0)
		{
			if ($remise->fk_facture)	// Protection against multiple submission
			{
				$this->error=$langs->trans("ErrorDiscountAlreadyUsed");
				$this->db->rollback();
				return -5;
			}

			$propalligne=new PropaleLigne($this->db);
			$propalligne->fk_propal=$this->id;
			$propalligne->fk_remise_except=$remise->id;
			$propalligne->desc=$remise->description;   	// Description ligne
			$propalligne->tva_tx=$remise->tva_tx;
			$propalligne->subprice=-$remise->amount_ht;
			$propalligne->fk_product=0;					// Id produit predefini
			$propalligne->qty=1;
			$propalligne->remise=0;
			$propalligne->remise_percent=0;
			$propalligne->rang=-1;
			$propalligne->info_bits=2;

			// TODO deprecated
			$propalligne->price=-$remise->amount_ht;

			$propalligne->total_ht  = -$remise->amount_ht;
			$propalligne->total_tva = -$remise->amount_tva;
			$propalligne->total_ttc = -$remise->amount_ttc;

			$result=$propalligne->insert();
			if ($result > 0)
			{
				$result=$this->update_price(1);
				if ($result > 0)
				{
					$this->db->commit();
					return 1;
				}
				else
				{
					$this->db->rollback();
					return -1;
				}
			}
			else
			{
				$this->error=$propalligne->error;
				$this->db->rollback();
				return -2;
			}
		}
		else
		{
			$this->db->rollback();
			return -2;
		}
	}

	/**
	 *    	Add a proposal line into database (linked to product/service or not)
	 * 		\param    	propalid        	Id de la propale
	 * 		\param    	desc            	Description de la ligne
	 * 		\param    	pu_ht              	Prix unitaire
	 * 		\param    	qty             	Quantite
	 * 		\param    	txtva           	Taux de tva
	 * 		\param		txlocaltax1			Local tax 1 rate
	 *  	\param		txlocaltax2			Local tax 2 rate
	 *		\param    	fk_product      	Id du produit/service predefini
	 * 		\param    	remise_percent  	Pourcentage de remise de la ligne
	 * 		\param    	price_base_type		HT or TTC
	 * 		\param    	pu_ttc             	Prix unitaire TTC
	 * 		\param    	info_bits			Bits de type de lignes
	 *      \param      type                Type of line (product, service)
	 *      \param      rang                Position of line
	 *    	\return    	int             	>0 if OK, <0 if KO
	 *    	@see       	add_product
	 * 		\remarks	Les parametres sont deja cense etre juste et avec valeurs finales a l'appel
	 *					de cette methode. Aussi, pour le taux tva, il doit deja avoir ete defini
	 *					par l'appelant par la methode get_default_tva(societe_vendeuse,societe_acheteuse,'',produit)
	 *					et le desc doit deja avoir la bonne valeur (a l'appelant de gerer le multilangue)
	 */
	function addline($propalid, $desc, $pu_ht, $qty, $txtva, $txlocaltax1=0, $txlocaltax2=0, $fk_product=0, $remise_percent=0, $price_base_type='HT', $pu_ttc=0, $info_bits=0, $type=0, $rang=-1, $special_code=0, $fk_parent_line=0)
	{
		global $conf;

		dol_syslog("Propal::Addline propalid=$propalid, desc=$desc, pu_ht=$pu_ht, qty=$qty, txtva=$txtva, fk_product=$fk_product, remise_except=$remise_percent, price_base_type=$price_base_type, pu_ttc=$pu_ttc, info_bits=$info_bits, type=$type");
		include_once(DOL_DOCUMENT_ROOT.'/lib/price.lib.php');

		// Clean parameters
		if (empty($remise_percent)) $remise_percent=0;
		if (empty($qty)) $qty=0;
		if (empty($info_bits)) $info_bits=0;
		if (empty($rang)) $rang=0;
		if (empty($fk_parent_line) || $fk_parent_line < 0) $fk_parent_line=0;

		$remise_percent=price2num($remise_percent);
		$qty=price2num($qty);
		$pu_ht=price2num($pu_ht);
		$pu_ttc=price2num($pu_ttc);
		$txtva=price2num($txtva);
		$txlocaltax1=price2num($txlocaltax1);
		$txlocaltax2=price2num($txlocaltax2);
		if ($price_base_type=='HT')
		{
			$pu=$pu_ht;
		}
		else
		{
			$pu=$pu_ttc;
		}

		// Check parameters
		if ($type < 0) return -1;

		if ($this->statut == 0)
		{
			$this->db->begin();

			// Calcul du total TTC et de la TVA pour la ligne a partir de
			// qty, pu, remise_percent et txtva
			// TRES IMPORTANT: C'est au moment de l'insertion ligne qu'on doit stocker
			// la part ht, tva et ttc, et ce au niveau de la ligne qui a son propre taux tva.
			$tabprice=calcul_price_total($qty, $pu, $remise_percent, $txtva, $txlocaltax1, $txlocaltax2, 0, $price_base_type, $info_bits);
			$total_ht  = $tabprice[0];
			$total_tva = $tabprice[1];
			$total_ttc = $tabprice[2];
			$total_localtax1 = $tabprice[9];
			$total_localtax2 = $tabprice[10];

			// Rang to use
			$rangtouse = $rang;
			if ($rangtouse == -1)
			{
				$rangmax = $this->line_max($fk_parent_line);
				$rangtouse = $rangmax + 1;
			}

			// TODO A virer
			// Anciens indicateurs: $price, $remise (a ne plus utiliser)
			$price = $pu;
			$remise = 0;
			if ($remise_percent > 0)
			{
				$remise = round(($pu * $remise_percent / 100), 2);
				$price = $pu - $remise;
			}

			// Insert line
			$this->line=new PropaleLigne($this->db);

			$this->line->fk_propal=$propalid;
			$this->line->desc=$desc;
			$this->line->qty=$qty;
			$this->line->tva_tx=$txtva;
			$this->line->localtax1_tx=$txlocaltax1;
			$this->line->localtax2_tx=$txlocaltax2;
			$this->line->fk_product=$fk_product;
			$this->line->remise_percent=$remise_percent;
			$this->line->subprice=$pu_ht;
			$this->line->rang=$rangtouse;
			$this->line->info_bits=$info_bits;
			$this->line->fk_remise_except=$fk_remise_except;
			$this->line->total_ht=$total_ht;
			$this->line->total_tva=$total_tva;
			$this->line->total_localtax1=$total_localtax1;
			$this->line->total_localtax2=$total_localtax2;
			$this->line->total_ttc=$total_ttc;
			$this->line->product_type=$type;
			$this->line->special_code=$special_code;
			$this->line->fk_parent_line=$fk_parent_line;

			// Mise en option de la ligne
			//if ($conf->global->PROPALE_USE_OPTION_LINE && !$qty) $ligne->special_code=3;
			if (empty($qty) && empty($special_code)) $this->line->special_code=3;

			// TODO deprecated
			$this->line->price=$price;
			$this->line->remise=$remise;

			$result=$this->line->insert();
			if ($result > 0)
			{
				// Reorder if child line
				if (! empty($fk_parent_line)) $this->line_order(true,'DESC');

				// Mise a jour informations denormalisees au niveau de la propale meme
				$result=$this->update_price(1);
				if ($result > 0)
				{
					$this->db->commit();
					return $this->line->rowid;
				}
				else
				{
					$this->error=$this->db->error();
					dol_syslog("Error sql=$sql, error=".$this->error,LOG_ERR);
					$this->db->rollback();
					return -1;
				}
			}
			else
			{
				$this->error=$this->line->error;
				$this->db->rollback();
				return -2;
			}
		}
	}


	/**
	 *    Update a proposal line
	 *    @param      rowid             Id de la ligne
	 *    @param      pu		        Prix unitaire (HT ou TTC selon price_base_type)
	 *    @param      qty             	Quantity
	 *    @param      remise_percent  	Remise effectuee sur le produit
	 *    @param      txtva	          	Taux de TVA
	 * 	  @param	  txlocaltax1		Local tax 1 rate
	 *    @param	  txlocaltax2		Local tax 2 rate
	 *    @param      desc            	Description
	 *	  @param	  price_base_type	HT ou TTC
	 *	  @param      info_bits        	Miscellanous informations
	 *	  @param      special_code      Set special code ('' = we don't change it)
	 *	  @param      fk_parent_line    Id of line parent
	 *    @return     int             	0 en cas de succes
	 */
	function updateline($rowid, $pu, $qty, $remise_percent=0, $txtva, $txlocaltax1=0, $txlocaltax2=0, $desc='', $price_base_type='HT', $info_bits=0, $special_code=0, $fk_parent_line=0, $skip_update_total=0)
	{
		global $conf,$user,$langs;

		dol_syslog("Propal::UpdateLine $rowid, $pu, $qty, $remise_percent, $txtva, $desc, $price_base_type, $info_bits");
		include_once(DOL_DOCUMENT_ROOT.'/lib/price.lib.php');

		// Clean parameters
		$remise_percent=price2num($remise_percent);
		$qty=price2num($qty);
		$pu = price2num($pu);
		$txtva = price2num($txtva);
		$txlocaltax1=price2num($txlocaltax1);
		$txlocaltax2=price2num($txlocaltax2);
		if (empty($qty) && empty($special_code)) $special_code=3;    // Set option tag
		if (! empty($qty) && $special_code == 3) $special_code=0;    // Remove option tag

		if ($this->statut == 0)
		{
			$this->db->begin();

			// Calcul du total TTC et de la TVA pour la ligne a partir de
			// qty, pu, remise_percent et txtva
			// TRES IMPORTANT: C'est au moment de l'insertion ligne qu'on doit stocker
			// la part ht, tva et ttc, et ce au niveau de la ligne qui a son propre taux tva.
			$tabprice=calcul_price_total($qty, $pu, $remise_percent, $txtva, $txlocaltax1, $txlocaltax2, 0, $price_base_type, $info_bits);
			$total_ht  = $tabprice[0];
			$total_tva = $tabprice[1];
			$total_ttc = $tabprice[2];
			$total_localtax1 = $tabprice[9];
			$total_localtax2 = $tabprice[10];

			// Anciens indicateurs: $price, $remise (a ne plus utiliser)
			$price = $pu;
			if ($remise_percent > 0)
			{
				$remise = round(($pu * $remise_percent / 100), 2);
				$price = $pu - $remise;
			}

			// Update line
			$this->line=new PropaleLigne($this->db);

			// Stock previous line records
			$staticline=new PropaleLigne($this->db);
			$staticline->fetch($rowid);
			$this->line->oldline = $staticline;

			$this->line->rowid=$rowid;
			$this->line->desc=$desc;
			$this->line->qty=$qty;
			$this->line->tva_tx=$txtva;
			$this->line->localtax1_tx=$txlocaltax1;
			$this->line->localtax2_tx=$txlocaltax2;
			$this->line->remise_percent=$remise_percent;
			$this->line->subprice=$pu;
			$this->line->info_bits=$info_bits;
			$this->line->total_ht=$total_ht;
			$this->line->total_tva=$total_tva;
			$this->line->total_localtax1=$total_localtax1;
			$this->line->total_localtax2=$total_localtax2;
			$this->line->total_ttc=$total_ttc;
			$this->line->special_code=$special_code;
			$this->line->fk_parent_line=$fk_parent_line;
			$this->line->skip_update_total=$skip_update_total;

			// TODO deprecated
			$this->line->price=$price;
			$this->line->remise=$remise;

			$result=$this->line->update();
			if ($result > 0)
			{
				$this->update_price(1);

				$this->fk_propal = $this->id;
				$this->rowid = $rowid;

				$this->db->commit();
				return $result;
			}
			else
			{
				$this->error=$this->db->error();
				$this->db->rollback();
				dol_syslog("Propal::UpdateLine Error=".$this->error, LOG_ERR);
				return -1;
			}
		}
		else
		{
			dol_syslog("Propal::UpdateLigne Erreur -2 Propal en mode incompatible pour cette action");
			return -2;
		}
	}


	/**
	 *      \brief      Supprime une ligne de detail
	 *      \param      idligne     Id de la ligne detail a supprimer
	 *      \return     int         >0 si ok, <0 si ko
	 */
	function deleteline($lineid)
	{
		if ($this->statut == 0)
		{
			$line=new PropaleLigne($this->db);

			// For triggers
			$line->fetch($lineid);

			if ($line->delete() > 0)
			{
				$this->update_price(1);

				return 1;
			}
			else
			{
				return -1;
			}
		}
		else
		{
			return -2;
		}
	}


	/**
	 *      Create commercial proposal into database
	 * 		this->ref can be set or empty. If empty, we will use "(PROVid)"
	 * 		@param		user		User that create
	 *      @return     int     	<0 if KO, >=0 if OK
	 */
	function create($user='', $notrigger=0)
	{
		global $langs,$conf,$mysoc;
		$error=0;

		$now=dol_now();

		// Clean parameters
		$this->fin_validite = $this->datep + ($this->duree_validite * 24 * 3600);
        if (empty($this->availability_id)) $this->availability_id=0;
        if (empty($this->demand_reason_id)) $this->demand_reason_id=0;

		dol_syslog("Propal::Create");

		// Check parameters
		$soc = new Societe($this->db);
		$result=$soc->fetch($this->socid);
		if ($result < 0)
		{
			$this->error="Failed to fetch company";
			dol_syslog("Propal::create ".$this->error, LOG_ERR);
			return -3;
		}
		if (! empty($this->ref))
		{
			$result=$this->verifyNumRef();	// Check ref is not yet used
		}


		$this->db->begin();

		$this->fetch_thirdparty();

		// Insert into database
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."propal (";
		$sql.= "fk_soc";
		$sql.= ", price";
		$sql.= ", remise";
		$sql.= ", remise_percent";
		$sql.= ", remise_absolue";
		$sql.= ", tva";
		$sql.= ", total";
		$sql.= ", datep";
		$sql.= ", datec";
		$sql.= ", ref";
		$sql.= ", fk_user_author";
		$sql.= ", note";
		$sql.= ", note_public";
		$sql.= ", model_pdf";
		$sql.= ", fin_validite";
		$sql.= ", fk_cond_reglement";
		$sql.= ", fk_mode_reglement";
		$sql.= ", ref_client";
		$sql.= ", date_livraison";
		$sql.= ", fk_availability";
		$sql.= ", fk_demand_reason";
		$sql.= ", entity";
		$sql.= ") ";
		$sql.= " VALUES (";
		$sql.= $this->socid;
		$sql.= ", 0";
		$sql.= ", ".$this->remise;
		$sql.= ", ".($this->remise_percent?$this->remise_percent:'null');
		$sql.= ", ".($this->remise_absolue?$this->remise_absolue:'null');
		$sql.= ", 0";
		$sql.= ", 0";
		$sql.= ", '".$this->db->idate($this->datep)."'";
		$sql.= ", '".$this->db->idate($now)."'";
		$sql.= ", '(PROV)'";
		$sql.= ", ".($user->id > 0 ? "'".$user->id."'":"null");
		$sql.= ", '".$this->db->escape($this->note)."'";
		$sql.= ", '".$this->db->escape($this->note_public)."'";
		$sql.= ", '".$this->modelpdf."'";
		$sql.= ", '".$this->db->idate($this->fin_validite)."'";
		$sql.= ", ".$this->cond_reglement_id;
		$sql.= ", ".$this->mode_reglement_id;
		$sql.= ", '".$this->db->escape($this->ref_client)."'";
		$sql.= ", ".($this->date_livraison!=''?"'".$this->db->idate($this->date_livraison)."'":'null');
		$sql.= ", ".$this->availability_id;
		$sql.= ", ".$this->demand_reason_id;
		$sql.= ", ".$conf->entity;
		$sql.= ")";

		dol_syslog("Propal::create sql=".$sql, LOG_DEBUG);
		$resql=$this->db->query($sql);
		if ($resql)
		{
			$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX."propal");

			if ($this->id)
			{
				if (empty($this->ref)) $this->ref='(PROV'.$this->id.')';
				$sql = 'UPDATE '.MAIN_DB_PREFIX."propal SET ref='".$this->ref."' WHERE rowid=".$this->id;

				dol_syslog("Propal::create sql=".$sql);
				$resql=$this->db->query($sql);
				if (! $resql) $error++;

				/*
				 *  Insertion du detail des produits dans la base
				 */
				if (! $error)
				{
					$fk_parent_line=0;
					$num=sizeof($this->lines);

					for ($i=0;$i<$num;$i++)
					{
						// Reset fk_parent_line for no child products and special product
						if (($this->lines[$i]->product_type != 9 && empty($this->lines[$i]->fk_parent_line)) || $this->lines[$i]->product_type == 9) {
							$fk_parent_line = 0;
						}

						$result = $this->addline(
						$this->id,
						$this->lines[$i]->desc,
						$this->lines[$i]->subprice,
						$this->lines[$i]->qty,
						$this->lines[$i]->tva_tx,
						$this->lines[$i]->localtax1_tx,
						$this->lines[$i]->localtax2_tx,
						$this->lines[$i]->fk_product,
						$this->lines[$i]->remise_percent,
						'HT',
						0,
						0,
						$this->lines[$i]->product_type,
						$this->lines[$i]->rang,
						$this->lines[$i]->special_code,
						$fk_parent_line
						);

						if ($result < 0)
						{
							$error++;
							$this->error=$this->db->error;
							dol_print_error($this->db);
							break;
						}
						// Defined the new fk_parent_line
						if ($result > 0 && $this->lines[$i]->product_type == 9) {
							$fk_parent_line = $result;
						}
					}
				}

				// Add linked object
				if (! $error && $this->origin && $this->origin_id)
				{
					$ret = $this->add_object_linked();
					if (! $ret)	dol_print_error($this->db);
				}

				// Affectation au projet
				if (! $error && $this->fk_project)
				{
					$sql = "UPDATE ".MAIN_DB_PREFIX."propal";
					$sql.= " SET fk_projet=".$this->fk_project;
					$sql.= " WHERE ref='".$this->ref."'";
					$sql.= " AND entity = ".$conf->entity;

					$result=$this->db->query($sql);
				}

				// Affectation de l'adresse de livraison
				if (! $error && $this->fk_delivery_address)
				{
					$sql = "UPDATE ".MAIN_DB_PREFIX."propal";
					$sql.= " SET fk_adresse_livraison = ".$this->fk_delivery_address;
					$sql.= " WHERE ref = '".$this->ref."'";
					$sql.= " AND entity = ".$conf->entity;

					$result=$this->db->query($sql);
				}

				if (! $error)
				{
					// Mise a jour infos denormalisees
					$resql=$this->update_price(1);
					if ($resql)
					{
						if (! $notrigger)
						{
							// Appel des triggers
							include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
							$interface=new Interfaces($this->db);
							$result=$interface->run_triggers('PROPAL_CREATE',$this,$user,$langs,$conf);
							if ($result < 0) { $error++; $this->errors=$interface->errors; }
							// Fin appel triggers
						}
					}
					else
					{
						$error++;
					}
				}
			}
			else
			{
				$error++;
			}

			if (! $error)
			{
				$this->db->commit();
				dol_syslog("Propal::Create done id=".$this->id);
				return $this->id;
			}
			else
			{
				$this->error=$this->db->error();
				dol_syslog("Propal::Create -2 ".$this->error, LOG_ERR);
				$this->db->rollback();
				return -2;
			}
		}
		else
		{
			$this->error=$this->db->error();
			dol_syslog("Propal::Create -1 ".$this->error, LOG_ERR);
			$this->db->rollback();
			return -1;
		}
	}


	/**
	 *    \brief     Insert en base un objet propal completement definie par ses donnees membres (resultant d'une copie par exemple).
	 *    \return    int                 l'id du nouvel objet propal en base si ok, <0 si ko
	 *    \see       create
	 */
	function create_from($user)
	{
		$this->products=$this->lines;

		return $this->create();
	}

	/**
	 *		Load an object from its id and create a new one in database
	 *		@param      fromid     		Id of object to clone
	 *		@param		invertdetail	Reverse sign of amounts for lines
	 *		@param		socid			Id of thirdparty
	 * 	 	@return		int				New id of clone
	 */
	function createFromClone($fromid,$invertdetail=0,$socid=0)
	{
		global $user,$langs,$conf;

		$error=0;

		$now=dol_now();

		$object=new Propal($this->db);

		// Instantiate hooks of thirdparty module
		if (is_array($conf->hooks_modules) && !empty($conf->hooks_modules))
		{
			$object->callHooks('propalcard');
		}

		$this->db->begin();

		// Load source object
		$object->fetch($fromid);
		$objFrom = $object;

		$objsoc=new Societe($this->db);

		// Change socid if needed
		if (! empty($socid) && $socid != $object->socid)
		{
			if ($objsoc->fetch($socid)>0)
			{
				$object->socid 					= $objsoc->id;
				$object->cond_reglement_id		= (! empty($objsoc->cond_reglement_id) ? $objsoc->cond_reglement_id : 0);
				$object->mode_reglement_id		= (! empty($objsoc->mode_reglement_id) ? $objsoc->mode_reglement_id : 0);
				$object->fk_project				= '';
				$object->fk_delivery_address	= '';
			}

			// TODO Change product price if multi-prices
		}
		else
		{
			$objsoc->fetch($object->socid);
		}

		$object->id=0;
		$object->statut=0;

		$objsoc->fetch($object->socid);

		if (empty($conf->global->PROPALE_ADDON) || ! is_readable(DOL_DOCUMENT_ROOT ."/includes/modules/propale/".$conf->global->PROPALE_ADDON.".php"))
		{
			$this->error='ErrorSetupNotComplete';
			return -1;
		}

		// Clear fields
		$object->user_author	= $user->id;
		$object->user_valid		= '';
		$object->date			= '';
		$object->datep			= $now;
		$object->fin_validite	= $object->datep + ($this->duree_validite * 24 * 3600);
		$object->ref_client		= '';

		// Set ref
		require_once(DOL_DOCUMENT_ROOT ."/includes/modules/propale/".$conf->global->PROPALE_ADDON.".php");
		$obj = $conf->global->PROPALE_ADDON;
		$modPropale = new $obj;
		$object->ref = $modPropale->getNextValue($objsoc,$object);

		// Create clone
		$result=$object->create($user);

		// Other options
		if ($result < 0)
		{
			$this->error=$object->error;
			$error++;
		}

		if (! $error)
		{
			// Hook for external modules
            if (! empty($object->hooks))
            {
            	foreach($object->hooks as $hook)
            	{
            		if (! empty($hook['modules']))
            		{
            			foreach($hook['modules'] as $module)
            			{
            				if (method_exists($module,'createfrom'))
            				{
            					$result = $module->createfrom($objFrom,$result,$object->element);
            					if ($result < 0) $error++;
            				}
            			}
            		}
            	}
            }

			// Appel des triggers
			include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
			$interface=new Interfaces($this->db);
			$result=$interface->run_triggers('PROPAL_CLONE',$object,$user,$langs,$conf);
			if ($result < 0) { $error++; $this->errors=$interface->errors; }
			// Fin appel triggers
		}

		// End
		if (! $error)
		{
			$this->db->commit();
			return $object->id;
		}
		else
		{
			$this->db->rollback();
			return -1;
		}
	}

	/**
	 *    	\brief      Load a proposal from database and its ligne array
	 *		\param      rowid       id of object to load
	 * 		\param		ref			Ref of proposal
	 *		\return     int         >0 if OK, <0 if KO
	 */
	function fetch($rowid,$ref='')
	{
		global $conf;

		$sql = "SELECT p.rowid,ref,remise,remise_percent,remise_absolue,fk_soc";
		$sql.= ", total, tva, localtax1, localtax2, total_ht";
		$sql.= ", datec";
		$sql.= ", date_valid as datev";
		$sql.= ", datep as dp";
		$sql.= ", fin_validite as dfv";
		$sql.= ", date_livraison as date_livraison";
		$sql.= ", ca.code as availability_code, ca.label as availability";
		$sql.= ", dr.code as demand_reason_code, dr.label as demand_reason";
		$sql.= ", model_pdf, ref_client";
		$sql.= ", note, note_public";
		$sql.= ", fk_projet, fk_statut";
		$sql.= ", fk_user_author, fk_user_valid, fk_user_cloture";
		$sql.= ", fk_adresse_livraison";
		$sql.= ", p.fk_availability";
		$sql.= ", p.fk_demand_reason";
		$sql.= ", p.fk_cond_reglement";
		$sql.= ", p.fk_mode_reglement";
		$sql.= ", c.label as statut_label";
		$sql.= ", cr.code as cond_reglement_code, cr.libelle as cond_reglement, cr.libelle_facture as cond_reglement_libelle_doc";
		$sql.= ", cp.code as mode_reglement_code, cp.libelle as mode_reglement";
		$sql.= " FROM ".MAIN_DB_PREFIX."c_propalst as c, ".MAIN_DB_PREFIX."propal as p";
		$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'c_paiement as cp ON p.fk_mode_reglement = cp.id';
		$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'c_payment_term as cr ON p.fk_cond_reglement = cr.rowid';
		$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'c_availability as ca ON p.fk_availability = ca.rowid';
		$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'c_input_reason as dr ON p.fk_demand_reason = dr.rowid';
		$sql.= " WHERE p.fk_statut = c.id";
		$sql.= " AND p.entity = ".$conf->entity;
		if ($ref) $sql.= " AND p.ref='".$ref."'";
		else $sql.= " AND p.rowid=".$rowid;

		dol_syslog("Propal::fecth sql=".$sql, LOG_DEBUG);
		$resql=$this->db->query($sql);
		if ($resql)
		{
			if ($this->db->num_rows($resql))
			{
				$obj = $this->db->fetch_object($resql);

				$this->id                   = $obj->rowid;

				$this->ref                  = $obj->ref;
				$this->ref_client           = $obj->ref_client;
				$this->remise               = $obj->remise;
				$this->remise_percent       = $obj->remise_percent;
				$this->remise_absolue       = $obj->remise_absolue;
				$this->total                = $obj->total; // TODO obsolete
				$this->total_ht             = $obj->total_ht;
				$this->total_tva            = $obj->tva;
				$this->total_localtax1		= $obj->localtax1;
				$this->total_localtax2		= $obj->localtax2;
				$this->total_ttc            = $obj->total;
				$this->socid                = $obj->fk_soc;
				$this->fk_project           = $obj->fk_projet;
				$this->modelpdf             = $obj->model_pdf;
				$this->note                 = $obj->note;
				$this->note_public          = $obj->note_public;
				$this->statut               = $obj->fk_statut;
				$this->statut_libelle       = $obj->statut_label;

				$this->datec                = $this->db->jdate($obj->datec);
				$this->datev                = $this->db->jdate($obj->datev);
				$this->date                 = $this->db->jdate($obj->dp);	// Proposal date
				$this->datep                = $this->db->jdate($obj->dp);
				$this->fin_validite         = $this->db->jdate($obj->dfv);
				$this->date_livraison       = $this->db->jdate($obj->date_livraison);
				$this->availability_id      = $obj->fk_availability;
				$this->availability_code    = $obj->availability_code;
				$this->availability         = $obj->availability;
				$this->demand_reason_id     = $obj->fk_demand_reason;
				$this->demand_reason_code   = $obj->demand_reason_code;
				$this->demand_reason        = $obj->demand_reason;
				$this->fk_delivery_address  = $obj->fk_adresse_livraison;	// TODO obsolete
				$this->fk_address  			= $obj->fk_adresse_livraison;

				$this->mode_reglement_id       = $obj->fk_mode_reglement;
				$this->mode_reglement_code     = $obj->mode_reglement_code;
				$this->mode_reglement          = $obj->mode_reglement;
				$this->cond_reglement_id       = $obj->fk_cond_reglement;
				$this->cond_reglement_code     = $obj->cond_reglement_code;
				$this->cond_reglement          = $obj->cond_reglement;
				$this->cond_reglement_doc      = $obj->cond_reglement_libelle_doc;

				$this->user_author_id = $obj->fk_user_author;
				$this->user_valid_id  = $obj->fk_user_valid;
				$this->user_close_id  = $obj->fk_user_cloture;

				if ($obj->fk_statut == 0)
				{
					$this->brouillon = 1;
				}

				$this->db->free($resql);

				$this->lines = array();

				/*
				 * Lignes propales liees a un produit ou non
				 */
				$sql = "SELECT d.rowid, d.fk_propal, d.fk_parent_line, d.description, d.price, d.tva_tx, d.localtax1_tx, d.localtax2_tx, d.qty, d.fk_remise_except, d.remise_percent, d.subprice, d.fk_product,";
				$sql.= " d.info_bits, d.total_ht, d.total_tva, d.total_localtax1, d.total_localtax2, d.total_ttc, d.marge_tx, d.marque_tx, d.special_code, d.rang, d.product_type,";
                $sql.= ' p.ref as product_ref, p.description as product_desc, p.fk_product_type, p.label as product_label';
				$sql.= " FROM ".MAIN_DB_PREFIX."propaldet as d";
				$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."product as p ON d.fk_product = p.rowid";
				$sql.= " WHERE d.fk_propal = ".$this->id;
				$sql.= " ORDER by d.rang";

				$result = $this->db->query($sql);
				if ($result)
				{
					$num = $this->db->num_rows($result);
					$i = 0;

					while ($i < $num)
					{
						$objp                   = $this->db->fetch_object($result);

						$line                   = new PropaleLigne($this->db);

						$line->rowid			= $objp->rowid;
						$line->fk_propal		= $objp->fk_propal;
						$line->fk_parent_line	= $objp->fk_parent_line;
						$line->product_type     = $objp->product_type;
						$line->desc             = $objp->description;  // Description ligne
						$line->qty              = $objp->qty;
						$line->tva_tx           = $objp->tva_tx;
						$line->localtax1_tx		= $objp->localtax1_tx;
						$line->localtax2_tx		= $objp->localtax2_tx;
						$line->subprice         = $objp->subprice;
						$line->fk_remise_except = $objp->fk_remise_except;
						$line->remise_percent   = $objp->remise_percent;
						$line->price            = $objp->price;		// TODO deprecated

						$line->info_bits        = $objp->info_bits;
						$line->total_ht         = $objp->total_ht;
						$line->total_tva        = $objp->total_tva;
						$line->total_localtax1	= $objp->total_localtax1;
						$line->total_localtax2	= $objp->total_localtax2;
						$line->total_ttc        = $objp->total_ttc;
						$line->marge_tx         = $objp->marge_tx;
						$line->marque_tx        = $objp->marque_tx;
						$line->special_code     = $objp->special_code;
						$line->rang             = $objp->rang;

						$line->fk_product       = $objp->fk_product;

                        $line->ref				= $objp->product_ref;		// TODO deprecated
                        $line->product_ref		= $objp->product_ref;
                        $line->libelle			= $objp->product_label;		// TODO deprecated
                        $line->label          	= $objp->product_label;		// TODO deprecated
                        $line->product_label	= $objp->product_label;
						$line->product_desc     = $objp->product_desc; 		// Description produit
                        $line->fk_product_type  = $objp->fk_product_type;

						$this->lines[$i]        = $line;
						//dol_syslog("1 ".$line->fk_product);
						//print "xx $i ".$this->lines[$i]->fk_product;
						$i++;
					}
					$this->db->free($result);
				}
				else
				{
					$this->error=$this->db->error();
					dol_syslog("Propal::Fetch Error ".$this->error, LOG_ERR);
					return -1;
				}

				return 1;
			}

			$this->error="Record Not Found";
			return 0;
		}
		else
		{
			$this->error=$this->db->error();
			dol_syslog("Propal::Fetch Error ".$this->error, LOG_ERR);
			return -1;
		}
	}

	/**
	 *      \brief      Passe au statut valider une propale
	 *      \param      user        Objet utilisateur qui valide
	 *      \return     int         <0 si ko, >=0 si ok
	 */
	function valid($user, $notrigger=0)
	{
		global $conf,$langs;

		$now=dol_now();

		if ($user->rights->propale->valider)
		{
			$this->db->begin();

			$sql = "UPDATE ".MAIN_DB_PREFIX."propal";
			$sql.= " SET fk_statut = 1, date_valid='".$this->db->idate($now)."', fk_user_valid=".$user->id;
			$sql.= " WHERE rowid = ".$this->id." AND fk_statut = 0";

			if ($this->db->query($sql))
			{
				$this->use_webcal=($conf->global->PHPWEBCALENDAR_PROPALSTATUS=='always'?1:0);

				if (! $notrigger)
				{
					// Appel des triggers
					include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
					$interface=new Interfaces($this->db);
					$result=$interface->run_triggers('PROPAL_VALIDATE',$this,$user,$langs,$conf);
					if ($result < 0) { $error++; $this->errors=$interface->errors; }
					// Fin appel triggers
				}

				if (! $error)
				{
					$this->brouillon=0;
					$this->statut = 1;
					$this->user_valid_id=$user->id;
					$this->datev=$now;
					$this->db->commit();
					return 1;
				}
				else
				{
					$this->db->rollback();
					return -2;
				}
			}
			else
			{
				$this->db->rollback();
				return -1;
			}
		}
	}


	/**
	 *      \brief      Define proposal date
	 *      \param      user        		Object user that modify
	 *      \param      date				Date
	 *      \return     int         		<0 if KO, >0 if OK
	 */
	function set_date($user, $date)
	{
		if ($user->rights->propale->creer)
		{
			$sql = "UPDATE ".MAIN_DB_PREFIX."propal SET datep = ".$this->db->idate($date);
			$sql.= " WHERE rowid = ".$this->id." AND fk_statut = 0";
			if ($this->db->query($sql) )
			{
				$this->date = $date;
				$this->datep = $date;
				return 1;
			}
			else
			{
				$this->error=$this->db->error();
				dol_syslog("Propal::set_date Erreur SQL".$this->error, LOG_ERR);
				return -1;
			}
		}
	}

	/**
	 *      \brief      Define end validity date
	 *      \param      user        		Object user that modify
	 *      \param      date_fin_validite	End of validity date
	 *      \return     int         		<0 if KO, >0 if OK
	 */
	function set_echeance($user, $date_fin_validite)
	{
		if ($user->rights->propale->creer)
		{
			$sql = "UPDATE ".MAIN_DB_PREFIX."propal SET fin_validite = ".$this->db->idate($date_fin_validite);
			$sql.= " WHERE rowid = ".$this->id." AND fk_statut = 0";
			if ($this->db->query($sql) )
			{
				$this->fin_validite = $date_fin_validite;
				return 1;
			}
			else
			{
				$this->error=$this->db->error();
				dol_syslog("Propal::set_echeance Erreur SQL".$this->error, LOG_ERR);
				return -1;
			}
		}
	}

	/**
	 *      \brief      Set delivery date
	 *      \param      user        		Objet utilisateur qui modifie
	 *      \param      date_livraison      date de livraison
	 *      \return     int         		<0 si ko, >0 si ok
	 */
	function set_date_livraison($user, $date_livraison)
	{
		if ($user->rights->propale->creer)
		{
			$sql = "UPDATE ".MAIN_DB_PREFIX."propal ";
			$sql.= " SET date_livraison = ".($date_livraison!=''?$this->db->idate($date_livraison):'null');
			$sql.= " WHERE rowid = ".$this->id;

			if ($this->db->query($sql))
			{
				$this->date_livraison = $date_livraison;
				return 1;
			}
			else
			{
				$this->error=$this->db->error();
				dol_syslog("Propal::set_date_livraison Erreur SQL");
				return -1;
			}
		}
	}

	/**
	 *      \brief      Definit une adresse de livraison
	 *      \param      user        		Objet utilisateur qui modifie
	 *      \param      adresse_livraison      Adresse de livraison
	 *      \return     int         		<0 si ko, >0 si ok
	 */
	function set_adresse_livraison($user, $fk_address)
	{
		if ($user->rights->propale->creer)
		{
			$sql = "UPDATE ".MAIN_DB_PREFIX."propal SET fk_adresse_livraison = '".$fk_address."'";
			$sql.= " WHERE rowid = ".$this->id." AND fk_statut = 0";

			if ($this->db->query($sql) )
			{
				$this->fk_delivery_address = $fk_address;
				return 1;
			}
			else
			{
				$this->error=$this->db->error();
				dol_syslog("Propal::set_adresse_livraison Erreur SQL");
				return -1;
			}
		}
	}

	/**
	 *      \brief      Set delivery
	 *      \param      user		  Objet utilisateur qui modifie
	 *      \param      delivery      delai de livraison
	 *      \return     int           <0 si ko, >0 si ok
	 */
	function set_availability($user, $id)
	{
		if ($user->rights->propale->creer)
		{
			$sql = "UPDATE ".MAIN_DB_PREFIX."propal ";
			$sql.= " SET fk_availability = '".$id."'";
			$sql.= " WHERE rowid = ".$this->id;

			if ($this->db->query($sql))
			{
				$this->fk_availability = $id;
				return 1;
			}
			else
			{
				$this->error=$this->db->error();
				dol_syslog("Propal::set_availability Erreur SQL");
				return -1;
			}
		}
	}

	/**
	 *      \brief      Set source of demand
	 *      \param      user		  Objet utilisateur qui modifie
	 *      \param      demand_reason  source of demand
	 *      \return     int           <0 si ko, >0 si ok
	 */
	function set_demand_reason($user, $id)
	{
		if ($user->rights->propale->creer)
		{
			$sql = "UPDATE ".MAIN_DB_PREFIX."propal ";
			$sql.= " SET fk_demand_reason = '".$id."'";
			$sql.= " WHERE rowid = ".$this->id;

			if ($this->db->query($sql))
			{
				$this->fk_demand_reason = $id;
				return 1;
			}
			else
			{
				$this->error=$this->db->error();
				dol_syslog("Propal::set_demand_reason Erreur SQL");
				return -1;
			}
		}
	}

	/**
	 *      \brief      Positionne numero reference client
	 *      \param      user            Utilisateur qui modifie
	 *      \param      ref_client      Reference client
	 *      \return     int             <0 si ko, >0 si ok
	 */
	function set_ref_client($user, $ref_client)
	{
		if ($user->rights->propale->creer)
		{
			dol_syslog('Propale::set_ref_client this->id='.$this->id.', ref_client='.$ref_client);

			$sql = 'UPDATE '.MAIN_DB_PREFIX.'propal SET ref_client = '.(empty($ref_client) ? 'NULL' : '\''.$this->db->escape($ref_client).'\'');
			$sql.= ' WHERE rowid = '.$this->id;
			if ($this->db->query($sql) )
			{
				$this->ref_client = $ref_client;
				return 1;
			}
			else
			{
				$this->error=$this->db->error();
				dol_syslog('Propale::set_ref_client Erreur '.$this->error.' - '.$sql);
				return -2;
			}
		}
		else
		{
			return -1;
		}
	}

	/**
	 *      \brief      Definit une remise globale relative sur la proposition
	 *      \param      user        Objet utilisateur qui modifie
	 *      \param      remise      Montant remise
	 *      \return     int         <0 si ko, >0 si ok
	 */
	function set_remise_percent($user, $remise)
	{
		$remise=trim($remise)?trim($remise):0;

		if ($user->rights->propale->creer)
		{
			$remise = price2num($remise);

			$sql = "UPDATE ".MAIN_DB_PREFIX."propal SET remise_percent = ".$remise;
			$sql.= " WHERE rowid = ".$this->id." AND fk_statut = 0";

			if ($this->db->query($sql) )
			{
				$this->remise_percent = $remise;
				$this->update_price(1);
				return 1;
			}
			else
			{
				$this->error=$this->db->error();
				dol_syslog("Propal::set_remise_percent Error sql=$sql");
				return -1;
			}
		}
	}


	/**
	 *      \brief      Definit une remise globale absolue sur la proposition
	 *      \param      user        Objet utilisateur qui modifie
	 *      \param      remise      Montant remise
	 *      \return     int         <0 si ko, >0 si ok
	 */
	function set_remise_absolue($user, $remise)
	{
		$remise=trim($remise)?trim($remise):0;

		if ($user->rights->propale->creer)
		{
			$remise = price2num($remise);

			$sql = "UPDATE ".MAIN_DB_PREFIX."propal ";
			$sql.= " SET remise_absolue = ".$remise;
			$sql.= " WHERE rowid = ".$this->id." AND fk_statut = 0";

			if ($this->db->query($sql) )
			{
				$this->remise_absolue = $remise;
				$this->update_price(1);
				return 1;
			}
			else
			{
				$this->error=$this->db->error();
				dol_syslog("Propal::set_remise_absolue Error sql=$sql");
				return -1;
			}
		}
	}


	/**
	 *      \brief      Cloture de la proposition commerciale
	 *      \param      user        Utilisateur qui cloture
	 *      \param      statut      Statut
	 *      \param      note        Commentaire
	 *      \return     int         <0 si ko, >0 si ok
	 */
	function cloture($user, $statut, $note)
	{
		global $langs,$conf;

		$this->statut = $statut;

		$this->db->begin();

		$sql = "UPDATE ".MAIN_DB_PREFIX."propal";
		$sql.= " SET fk_statut = ".$statut.", note = '".$this->db->escape($note)."', date_cloture=".$this->db->idate(mktime()).", fk_user_cloture=".$user->id;
		$sql.= " WHERE rowid = ".$this->id;

		$resql=$this->db->query($sql);
		if ($resql)
		{
			if ($statut == 2)
			{
				// Classe la societe rattachee comme client
				$soc=new Societe($this->db);
				$soc->id = $this->socid;
				$result=$soc->set_as_client();

				if ($result < 0)
				{
					$this->error=$this->db->error();
					$this->db->rollback();
					return -2;
				}

				$this->use_webcal=($conf->global->PHPWEBCALENDAR_PROPALSTATUS=='always'?1:0);

				// Appel des triggers
				include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
				$interface=new Interfaces($this->db);
				$result=$interface->run_triggers('PROPAL_CLOSE_SIGNED',$this,$user,$langs,$conf);
				if ($result < 0) { $error++; $this->errors=$interface->errors; }
				// Fin appel triggers
			}
			else
			{
				$this->use_webcal=($conf->global->PHPWEBCALENDAR_PROPALSTATUS=='always'?1:0);

				// Appel des triggers
				include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
				$interface=new Interfaces($this->db);
				$result=$interface->run_triggers('PROPAL_CLOSE_REFUSED',$this,$user,$langs,$conf);
				if ($result < 0) { $error++; $this->errors=$interface->errors; }
				// Fin appel triggers
			}

			$this->db->commit();
			return 1;
		}
		else
		{
			$this->error=$this->db->error();
			$this->db->rollback();
			return -1;
		}
	}

	/**
	 *        \brief      Classe la propale comme facturee
	 *        \return     int     <0 si ko, >0 si ok
	 */
	function classer_facturee()
	{
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'propal SET fk_statut = 4';
		$sql .= ' WHERE rowid = '.$this->id.' AND fk_statut > 0 ;';
		if ($this->db->query($sql) )
		{
			return 1;
		}
		else
		{
			dol_print_error($this->db);
		}
	}

	/**
	 *		\brief		Set draft status
	 *		\param		user		Object user that modify
	 *		\param		int			<0 if KO, >0 if OK
	 */
	function set_draft($user)
	{
		global $conf,$langs;

		$sql = "UPDATE ".MAIN_DB_PREFIX."propal SET fk_statut = 0";
		$sql.= " WHERE rowid = ".$this->id;

		if ($this->db->query($sql))
		{
			return 1;
		}
		else
		{
			return -1;
		}
	}


	/**
	 *    \brief       Return list of proposal (eventually filtered on user) into an array
	 *    \param       shortlist       0=Return array[id]=ref, 1=Return array[](id=>id,ref=>ref)
	 *    \param       draft		   0=not draft, 1=draft
	 *    \param       notcurrentuser  0=current user, 1=not current user
	 *    \param       socid           Id third pary
	 *    \param       limit           For pagination
	 *    \param       offset          For pagination
	 *    \param       sortfield       Sort criteria
	 *    \param       sortorder       Sort order
	 *    \return      int		       -1 if KO, array with result if OK
	 */
	function liste_array($shortlist=0, $draft=0, $notcurrentuser=0, $socid=0, $limit=0, $offset=0, $sortfield='p.datep', $sortorder='DESC')
	{
		global $conf,$user;

		$ga = array();

		$sql = "SELECT s.nom, s.rowid, p.rowid as propalid, p.fk_statut, p.total_ht, p.ref, p.remise, ";
		$sql.= " p.datep as dp, p.fin_validite as datelimite";
		$sql.= " FROM ".MAIN_DB_PREFIX."societe as s, ".MAIN_DB_PREFIX."propal as p, ".MAIN_DB_PREFIX."c_propalst as c";
		$sql.= " WHERE p.entity = ".$conf->entity;
		$sql.= " AND p.fk_soc = s.rowid";
		$sql.= " AND p.fk_statut = c.id";
		if ($socid) $sql.= " AND s.rowid = ".$socid;
		if ($draft)	$sql.= " AND p.fk_statut = 0";
		if ($notcurrentuser) $sql.= " AND p.fk_user_author <> ".$user->id;
		$sql.= $this->db->order($sortfield,$sortorder);
		$sql.= $this->db->plimit($limit,$offset);

		$result=$this->db->query($sql);
		if ($result)
		{
			$num = $this->db->num_rows($result);
			if ($num)
			{
				$i = 0;
				while ($i < $num)
				{
					$obj = $this->db->fetch_object($result);

					if ($shortlist)
					{
						$ga[$obj->propalid] = $obj->ref;
					}
					else
					{
						$ga[$i]['id']	= $obj->propalid;
						$ga[$i]['ref'] 	= $obj->ref;
					}

					$i++;
				}
			}
			return $ga;
		}
		else
		{
			dol_print_error($this->db);
			return -1;
		}
	}

	/**
	 *    	\brief      Renvoie un tableau contenant les numeros de factures associees
	 *		\return		array		Tableau des id de factures
	 */
	function getInvoiceArrayList()
	{
		return $this->InvoiceArrayList($this->id);
	}

	/**
	 *    	\brief      Renvoie un tableau contenant les id et ref des factures associees
	 *		\param		id			Id propal
	 *		\return		array		Tableau des id de factures
	 */
	function InvoiceArrayList($id)
	{
		$ga = array();
		$linkedInvoices = array();

		$this->fetchObjectLinked($id,$this->element);
		foreach($this->linkedObjectsIds as $objecttype => $objectid)
		{
			$numi=sizeof($objectid);
			for ($i=0;$i<$numi;$i++)
			{
				// Cas des factures liees directement
				if ($objecttype == 'facture')
				{
					$linkedInvoices[] = $objectid[$i];
				}
				// Cas des factures liees via la commande
				else
				{
					$this->fetchObjectLinked($objectid[$i],$objecttype);
					foreach($this->linkedObjectsIds as $subobjecttype => $subobjectid)
					{
						$numj=sizeof($subobjectid);
						for ($j=0;$j<$numj;$j++)
						{
							$linkedInvoices[] = $subobjectid[$j];
						}
					}
				}
			}
		}

		if (sizeof($linkedInvoices) > 0)
		{
    		$sql= "SELECT rowid as facid, facnumber, total, datef as df, fk_user_author, fk_statut, paye";
    		$sql.= " FROM ".MAIN_DB_PREFIX."facture";
    		$sql.= " WHERE rowid IN (".implode(',',$linkedInvoices).")";

    		dol_syslog("Propal::InvoiceArrayList sql=".$sql);
    		$resql=$this->db->query($sql);

    		if ($resql)
    		{
    			$tab_sqlobj=array();
    			$nump = $this->db->num_rows($resql);
    			for ($i = 0;$i < $nump;$i++)
    			{
    				$sqlobj = $this->db->fetch_object($resql);
    				$tab_sqlobj[] = $sqlobj;
    			}
    			$this->db->free($resql);

    			$nump = sizeOf($tab_sqlobj);

    			if ($nump)
    			{
    				$i = 0;
    				while ($i < $nump)
    				{
    					$obj = array_shift($tab_sqlobj);

    					$ga[$i] = $obj;

    					$i++;
    				}
    			}
    			return $ga;
    		}
    		else
    		{
    			return -1;
    		}
		}
		else return $ga;
	}

	/**
	 *    \brief      Efface propal
	 *    \param      user        Objet du user qui efface
	 */
	function delete($user, $notrigger=0)
	{
		global $conf,$langs;
        require_once(DOL_DOCUMENT_ROOT."/lib/files.lib.php");

		$error=0;

		$this->db->begin();

		$sql = "DELETE FROM ".MAIN_DB_PREFIX."propaldet WHERE fk_propal = ".$this->id;
		if ( $this->db->query($sql) )
		{
			$sql = "DELETE FROM ".MAIN_DB_PREFIX."propal WHERE rowid = ".$this->id;
			if ( $this->db->query($sql) )
			{
				// Delete linked contacts
				$res = $this->delete_linked_contact();
				if ($res < 0)
				{
					$this->error='ErrorFailToDeleteLinkedContact';
					$this->db->rollback();
					return 0;
				}

				// We remove directory
				$propalref = dol_sanitizeFileName($this->ref);
				if ($conf->propale->dir_output)
				{
					$dir = $conf->propale->dir_output . "/" . $propalref ;
					$file = $conf->propale->dir_output . "/" . $propalref . "/" . $propalref . ".pdf";
					if (file_exists($file))
					{
						propale_delete_preview($this->db, $this->id, $this->ref);

						if (!dol_delete_file($file))
						{
							$this->error='ErrorFailToDeleteFile';
							$this->db->rollback();
							return 0;
						}
					}
					if (file_exists($dir))
					{
						$res=@dol_delete_dir($dir);
						if (! $res)
						{
							$this->error='ErrorFailToDeleteDir';
							$this->db->rollback();
							return 0;
						}
					}
				}

				if (! $notrigger)
				{
					// Call triggers
					include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
					$interface=new Interfaces($this->db);
					$result=$interface->run_triggers('PROPAL_DELETE',$this,$user,$langs,$conf);
					if ($result < 0) { $error++; $this->errors=$interface->errors; }
					// End call triggers
				}

				if (!$error)
				{
					dol_syslog("Suppression de la proposition $this->id par $user->id", LOG_DEBUG);
					$this->db->commit();
					return 1;
				}
				else
				{
					$this->db->rollback();
					return 0;
				}
			}
			else
			{
				$this->db->rollback();
				return -2;
			}
		}
		else
		{
			$this->db->rollback();
			return -1;
		}
	}


	/**
	 *   \brief      Change les conditions de reglement de la facture
	 *   \param      cond_reglement_id      Id de la nouvelle condition de reglement
	 *   \return     int                    >0 si ok, <0 si ko
	 */
	function cond_reglement($cond_reglement_id)
	{
		dol_syslog('Propale::cond_reglement('.$cond_reglement_id.')');
		if ($this->statut >= 0)
		{
			$sql = 'UPDATE '.MAIN_DB_PREFIX.'propal';
			$sql .= ' SET fk_cond_reglement = '.$cond_reglement_id;
			$sql .= ' WHERE rowid='.$this->id;
			if ( $this->db->query($sql) )
			{
				$this->cond_reglement_id = $cond_reglement_id;
				return 1;
			}
			else
			{
				dol_syslog('Propale::cond_reglement Erreur '.$sql.' - '.$this->db->error());
				$this->error=$this->db->error();
				return -1;
			}
		}
		else
		{
			dol_syslog('Propale::cond_reglement, etat propale incompatible');
			$this->error='Etat propale incompatible '.$this->statut;
			return -2;
		}
	}


	/**
	 *   \brief      Change le mode de reglement
	 *   \param      mode_reglement     Id du nouveau mode
	 *   \return     int         		>0 si ok, <0 si ko
	 */
	function mode_reglement($mode_reglement_id)
	{
		dol_syslog('Propale::mode_reglement('.$mode_reglement_id.')');
		if ($this->statut >= 0)
		{
			$sql = 'UPDATE '.MAIN_DB_PREFIX.'propal';
			$sql .= ' SET fk_mode_reglement = '.$mode_reglement_id;
			$sql .= ' WHERE rowid='.$this->id;
			if ( $this->db->query($sql) )
			{
				$this->mode_reglement_id = $mode_reglement_id;
				return 1;
			}
			else
			{
				dol_syslog('Propale::mode_reglement Erreur '.$sql.' - '.$this->db->error());
				$this->error=$this->db->error();
				return -1;
			}
		}
		else
		{
			dol_syslog('Propale::mode_reglement, etat propale incompatible');
			$this->error='Etat facture incompatible '.$this->statut;
			return -2;
		}
	}

/**
	 *   \brief      Change le delai de livraison
	 *   \param      availability_id      Id du nouveau delai de livraison
	 *   \return     int                    >0 si ok, <0 si ko
	 */
	function availability($availability_id)
	{
		dol_syslog('Propale::availability('.$availability_id.')');
		if ($this->statut >= 0)
		{
			$sql = 'UPDATE '.MAIN_DB_PREFIX.'propal';
			$sql .= ' SET fk_availability = '.$availability_id;
			$sql .= ' WHERE rowid='.$this->id;
			if ( $this->db->query($sql) )
			{
				$this->availability_id = $availability_id;
				return 1;
			}
			else
			{
				dol_syslog('Propale::availability Erreur '.$sql.' - '.$this->db->error());
				$this->error=$this->db->error();
				return -1;
			}
		}
		else
		{
			dol_syslog('Propale::availability, etat propale incompatible');
			$this->error='Etat propale incompatible '.$this->statut;
			return -2;
		}
	}

	/**
	 *   \brief      Change l'origine de la demande
	 *   \param      demand_reason_id      Id de la nouvelle origine de demande
	 *   \return     int                    >0 si ok, <0 si ko
	 */
	function demand_reason($demand_reason_id)
	{
		dol_syslog('Propale::demand_reason('.$demand_reason_id.')');
		if ($this->statut >= 0)
		{
			$sql = 'UPDATE '.MAIN_DB_PREFIX.'propal';
			$sql .= ' SET fk_demand_reason = '.$demand_reason_id;
			$sql .= ' WHERE rowid='.$this->id;
			if ( $this->db->query($sql) )
			{
				$this->demand_reason_id = $demand_reason_id;
				return 1;
			}
			else
			{
				dol_syslog('Propale::demand_reason Erreur '.$sql.' - '.$this->db->error());
				$this->error=$this->db->error();
				return -1;
			}
		}
		else
		{
			dol_syslog('Propale::demand_reason, etat propale incompatible');
			$this->error='Etat propale incompatible '.$this->statut;
			return -2;
		}
	}


	/**
	 *      \brief      Information sur l'objet propal
	 *      \param      id      id de la propale
	 */
	function info($id)
	{
		$sql = "SELECT c.rowid, ";
		$sql.= " c.datec, c.date_valid as datev, c.date_cloture as dateo,";
		$sql.= " c.fk_user_author, c.fk_user_valid, c.fk_user_cloture";
		$sql.= " FROM ".MAIN_DB_PREFIX."propal as c";
		$sql.= " WHERE c.rowid = ".$id;

		$result = $this->db->query($sql);

		if ($result)
		{
			if ($this->db->num_rows($result))
			{
				$obj = $this->db->fetch_object($result);

				$this->id                = $obj->rowid;

				$this->date_creation     = $this->db->jdate($obj->datec);
				$this->date_validation   = $this->db->jdate($obj->datev);
				$this->date_cloture      = $this->db->jdate($obj->dateo);

				$cuser = new User($this->db);
				$cuser->fetch($obj->fk_user_author);
				$this->user_creation     = $cuser;

				if ($obj->fk_user_valid)
				{
					$vuser = new User($this->db);
					$vuser->fetch($obj->fk_user_valid);
					$this->user_validation     = $vuser;
				}

				if ($obj->fk_user_cloture)
				{
					$cluser = new User($this->db);
					$cluser->fetch($obj->fk_user_cloture);
					$this->user_cloture     = $cluser;
				}


	  }
	  $this->db->free($result);

		}
		else
		{
			dol_print_error($this->db);
		}
	}


	/**
	 *    	Return label of status of proposal (draft, validated, ...)
	 *    	@param      mode        0=long label, 1=short label, 2=Picto + short label, 3=Picto, 4=Picto + long label, 5=Short label + Picto
	 *    	@return     string		Label
	 */
	function getLibStatut($mode=0)
	{
		return $this->LibStatut($this->statut,$mode);
	}

	/**
	 *    	Return label of a status (draft, validated, ...)
	 *    	@param      statut		id statut
	 *    	@param      mode        0=long label, 1=short label, 2=Picto + short label, 3=Picto, 4=Picto + long label, 5=Short label + Picto
	 *    	@return     string		Label
	 */
	function LibStatut($statut,$mode=1)
	{
		global $langs;
		$langs->load("propal");

		if ($mode == 0)
		{
			return $this->labelstatut[$statut];
		}
		if ($mode == 1)
		{
			return $this->labelstatut_short[$statut];
		}
		if ($mode == 2)
		{
			if ($statut==0) return img_picto($langs->trans('PropalStatusDraftShort'),'statut0').' '.$this->labelstatut_short[$statut];
			if ($statut==1) return img_picto($langs->trans('PropalStatusOpenedShort'),'statut1').' '.$this->labelstatut_short[$statut];
			if ($statut==2) return img_picto($langs->trans('PropalStatusSignedShort'),'statut3').' '.$this->labelstatut_short[$statut];
			if ($statut==3) return img_picto($langs->trans('PropalStatusNotSignedShort'),'statut5').' '.$this->labelstatut_short[$statut];
			if ($statut==4) return img_picto($langs->trans('PropalStatusBilledShort'),'statut6').' '.$this->labelstatut_short[$statut];
		}
		if ($mode == 3)
		{
			if ($statut==0) return img_picto($langs->trans('PropalStatusDraftShort'),'statut0');
			if ($statut==1) return img_picto($langs->trans('PropalStatusOpenedShort'),'statut1');
			if ($statut==2) return img_picto($langs->trans('PropalStatusSignedShort'),'statut3');
			if ($statut==3) return img_picto($langs->trans('PropalStatusNotSignedShort'),'statut5');
			if ($statut==4) return img_picto($langs->trans('PropalStatusBilledShort'),'statut6');
		}
		if ($mode == 4)
		{
			if ($statut==0) return img_picto($langs->trans('PropalStatusDraft'),'statut0').' '.$this->labelstatut[$statut];
			if ($statut==1) return img_picto($langs->trans('PropalStatusOpened'),'statut1').' '.$this->labelstatut[$statut];
			if ($statut==2) return img_picto($langs->trans('PropalStatusSigned'),'statut3').' '.$this->labelstatut[$statut];
			if ($statut==3) return img_picto($langs->trans('PropalStatusNotSigned'),'statut5').' '.$this->labelstatut[$statut];
			if ($statut==4) return img_picto($langs->trans('PropalStatusBilled'),'statut6').' '.$this->labelstatut[$statut];
		}
		if ($mode == 5)
		{
			if ($statut==0) return $this->labelstatut_short[$statut].' '.img_picto($langs->trans('PropalStatusDraftShort'),'statut0');
			if ($statut==1) return $this->labelstatut_short[$statut].' '.img_picto($langs->trans('PropalStatusOpenedShort'),'statut1');
			if ($statut==2) return $this->labelstatut_short[$statut].' '.img_picto($langs->trans('PropalStatusSignedShort'),'statut3');
			if ($statut==3) return $this->labelstatut_short[$statut].' '.img_picto($langs->trans('PropalStatusNotSignedShort'),'statut5');
			if ($statut==4) return $this->labelstatut_short[$statut].' '.img_picto($langs->trans('PropalStatusBilledShort'),'statut6');
		}
	}


	/**
     *      Load indicators for dashboard (this->nbtodo and this->nbtodolate)
     *      @param          user    Objet user
     *      @param          mode    "opened" pour propal a fermer, "signed" pour propale a facturer
     *      @return         int     <0 if KO, >0 if OK
	 */
	function load_board($user,$mode)
	{
		global $conf, $user;

		$now=gmmktime();

		$this->nbtodo=$this->nbtodolate=0;
		$clause = " WHERE";

		$sql = "SELECT p.rowid, p.ref, p.datec as datec, p.fin_validite as datefin";
		$sql.= " FROM ".MAIN_DB_PREFIX."propal as p";
		if (!$user->rights->societe->client->voir && !$user->societe_id)
		{
			$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."societe_commerciaux as sc ON p.fk_soc = sc.fk_soc";
			$sql.= " WHERE sc.fk_user = " .$user->id;
			$clause = " AND";
		}
		$sql.= $clause." p.entity = ".$conf->entity;
		if ($mode == 'opened') $sql.= " AND p.fk_statut = 1";
		if ($mode == 'signed') $sql.= " AND p.fk_statut = 2";
		if ($user->societe_id) $sql.= " AND p.fk_soc = ".$user->societe_id;

		$resql=$this->db->query($sql);
		if ($resql)
		{
			if ($mode == 'opened') $delay_warning=$conf->propal->cloture->warning_delay;
			if ($mode == 'signed') $delay_warning=$conf->propal->facturation->warning_delay;

			while ($obj=$this->db->fetch_object($resql))
			{
				$this->nbtodo++;
				if ($mode == 'opened')
				{
					$datelimit = $this->db->jdate($obj->datefin);
					if ($datelimit < ($now - $delay_warning))
					{
						$this->nbtodolate++;
					}
				}
				// \todo Definir regle des propales a facturer en retard
				// if ($mode == 'signed' && ! sizeof($this->FactureListeArray($obj->rowid))) $this->nbtodolate++;
			}
			return 1;
		}
		else
		{
			$this->error=$this->db->error();
			return -1;
		}
	}


	/**
	 *		Initialise an example of instance with random values
	 *		Used to build previews or test instances
	 */
	function initAsSpecimen()
	{
		global $user,$langs,$conf;

		// Charge tableau des produits prodids
		$prodids = array();
		$sql = "SELECT rowid";
		$sql.= " FROM ".MAIN_DB_PREFIX."product";
		$sql.= " WHERE entity = ".$conf->entity;
		$resql = $this->db->query($sql);
		if ($resql)
		{
			$num_prods = $this->db->num_rows($resql);
			$i = 0;
			while ($i < $num_prods)
			{
				$i++;
				$row = $this->db->fetch_row($resql);
				$prodids[$i] = $row[0];
			}
		}

		// Initialise parametres
		$this->id=0;
		$this->ref = 'SPECIMEN';
		$this->ref_client='NEMICEPS';
		$this->specimen=1;
		$this->socid = 1;
		$this->date = time();
		$this->fin_validite = $this->date+3600*24*30;
		$this->cond_reglement_id   = 1;
		$this->cond_reglement_code = 'RECEP';
		$this->mode_reglement_id   = 7;
		$this->mode_reglement_code = 'CHQ';
		$this->availability_id     = 1;
		$this->availability_code   = 'DSP';
		$this->demand_reason_id    = 1;
		$this->demand_reason_code  = 'SRC_00';
		$this->note_public='This is a comment (public)';
		$this->note='This is a comment (private)';
		// Lines
		$nbp = 5;
		$xnbp = 0;
		while ($xnbp < $nbp)
		{
			$line=new PropaleLigne($this->db);
			$line->desc=$langs->trans("Description")." ".$xnbp;
			$line->qty=1;
			$line->subprice=100;
			$line->price=100;
			$line->tva_tx=19.6;
			$line->total_ht=100;
			$line->total_ttc=119.6;
			$line->total_tva=19.6;
			$prodid = rand(1, $num_prods);
			$line->fk_product=$prodids[$prodid];

			$this->lines[$xnbp]=$line;

			$xnbp++;
		}

		$this->amount_ht      = $xnbp*100;
		$this->total_ht       = $xnbp*100;
		$this->total_tva      = $xnbp*19.6;
		$this->total_ttc      = $xnbp*119.6;
	}

	/**
	 *      \brief      Charge indicateurs this->nb de tableau de bord
	 *      \return     int         <0 si ko, >0 si ok
	 */
	function load_state_board()
	{
		global $conf, $user;

		$this->nb=array();
		$clause = "WHERE";

		$sql = "SELECT count(p.rowid) as nb";
		$sql.= " FROM ".MAIN_DB_PREFIX."propal as p";
		$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON p.fk_soc = s.rowid";
		if (!$user->rights->societe->client->voir && !$user->societe_id)
		{
			$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."societe_commerciaux as sc ON s.rowid = sc.fk_soc";
			$sql.= " WHERE sc.fk_user = " .$user->id;
			$clause = "AND";
		}
		$sql.= " ".$clause." p.entity = ".$conf->entity;

		$resql=$this->db->query($sql);
		if ($resql)
		{
			while ($obj=$this->db->fetch_object($resql))
			{
				$this->nb["proposals"]=$obj->nb;
			}
			return 1;
		}
		else
		{
			dol_print_error($this->db);
			$this->error=$this->db->error();
			return -1;
		}
	}


	/**
	 *      \brief      Renvoie la reference de propale suivante non utilisee en fonction du module
	 *                  de numerotation actif defini dans PROPALE_ADDON
	 *      \param	    soc  		            objet societe
	 *      \return     string              reference libre pour la propale
	 */
	function getNextNumRef($soc)
	{
		global $conf, $db, $langs;
		$langs->load("propal");

		$dir = DOL_DOCUMENT_ROOT . "/includes/modules/propale/";

		if (! empty($conf->global->PROPALE_ADDON))
		{
			$file = $conf->global->PROPALE_ADDON.".php";

			// Chargement de la classe de numerotation
			$classname = $conf->global->PROPALE_ADDON;
			require_once($dir.$file);

			$obj = new $classname();

			$numref = "";
			$numref = $obj->getNextValue($soc,$this);

			if ( $numref != "")
			{
				return $numref;
			}
			else
			{
				dol_print_error($db,"Propale::getNextNumRef ".$obj->error);
				return "";
			}
		}
		else
		{
			print $langs->trans("Error")." ".$langs->trans("Error_PROPALE_ADDON_NotDefined");
			return "";
		}
	}

	/**
	 *      Return clicable link of object (with eventually picto)
	 *      @param      withpicto       Add picto into link
	 *      @param      option          Where point the link
	 *      @param      get_params      Parametres added to url
	 *      @return     string          String with URL
	 */
	function getNomUrl($withpicto=0,$option='', $get_params='')
	{
		global $langs;

		$result='';
		if($option == '')
		{
			$lien = '<a href="'.DOL_URL_ROOT.'/comm/propal.php?id='.$this->id. $get_params .'">';
		}
		if($option == 'compta')   // deprecated
		{
			$lien = '<a href="'.DOL_URL_ROOT.'/comm/propal.php?id='.$this->id. $get_params .'">';
		}
		if($option == 'expedition')
		{
			$lien = '<a href="'.DOL_URL_ROOT.'/expedition/propal.php?id='.$this->id. $get_params .'">';
		}
		$lienfin='</a>';

		$picto='propal';
		$label=$langs->trans("ShowPropal").': '.$this->ref;

		if ($withpicto) $result.=($lien.img_object($label,$picto).$lienfin);
		if ($withpicto && $withpicto != 2) $result.=' ';
		$result.=$lien.$this->ref.$lienfin;
		return $result;
	}

	/**
	 * 	Return an array of propal lines
	 */
	function getLinesArray()
	{
		$sql = 'SELECT pt.rowid, pt.description, pt.fk_product, pt.fk_remise_except,';
		$sql.= ' pt.qty, pt.tva_tx, pt.remise_percent, pt.subprice, pt.info_bits,';
		$sql.= ' pt.total_ht, pt.total_tva, pt.total_ttc, pt.marge_tx, pt.marque_tx, pt.pa_ht, pt.special_code,';
		$sql.= ' pt.date_start, pt.date_end, pt.product_type, pt.rang,';
		$sql.= ' p.label as product_label, p.ref, p.fk_product_type, p.rowid as prodid,';
		$sql.= ' p.description as product_desc';
		$sql.= ' FROM '.MAIN_DB_PREFIX.'propaldet as pt';
		$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'product as p ON pt.fk_product=p.rowid';
		$sql.= ' WHERE pt.fk_propal = '.$this->id;
		$sql.= ' ORDER BY pt.rang ASC, pt.rowid';

		$resql = $this->db->query($sql);
		if ($resql)
		{
			$num = $this->db->num_rows($resql);
			$i = 0;

			while ($i < $num)
			{
				$obj = $this->db->fetch_object($resql);

				$this->lines[$i]->id				= $obj->rowid;
				$this->lines[$i]->description 		= $obj->description;
				$this->lines[$i]->fk_product		= $obj->fk_product;
				$this->lines[$i]->ref				= $obj->ref;
				$this->lines[$i]->product_label		= $obj->product_label;
				$this->lines[$i]->product_desc		= $obj->product_desc;
				$this->lines[$i]->fk_product_type	= $obj->fk_product_type;  // deprecated
				$this->lines[$i]->product_type		= $obj->product_type;
				$this->lines[$i]->qty				= $obj->qty;
				$this->lines[$i]->subprice			= $obj->subprice;
				$this->lines[$i]->pa_ht				= $obj->pa_ht;
				$this->lines[$i]->fk_remise_except 	= $obj->fk_remise_except;
				$this->lines[$i]->remise_percent	= $obj->remise_percent;
				$this->lines[$i]->tva_tx			= $obj->tva_tx;
				$this->lines[$i]->info_bits			= $obj->info_bits;
				$this->lines[$i]->total_ht			= $obj->total_ht;
				$this->lines[$i]->total_tva			= $obj->total_tva;
				$this->lines[$i]->total_ttc			= $obj->total_ttc;
				$this->lines[$i]->marge_tx			= $obj->marge_tx;
				$this->lines[$i]->marque_tx			= $obj->marque_tx;
				$this->lines[$i]->special_code		= $obj->special_code;
				$this->lines[$i]->rang				= $obj->rang;
				$this->lines[$i]->date_start		= $this->db->jdate($obj->date_start);
				$this->lines[$i]->date_end			= $this->db->jdate($obj->date_end);

				$i++;
			}
			$this->db->free($resql);

			return 1;
		}
		else
		{
			$this->error=$this->db->error();
			dol_syslog("Error sql=$sql, error=".$this->error,LOG_ERR);
			return -1;
		}
	}

}


/**
 *	\class      PropaleLigne
 *	\brief      Class to manage commercial proposal lines
 */
class PropaleLigne
{
	var $db;
	var $error;

	var $oldline;

	// From llx_propaldet
	var $rowid;
	var $fk_propal;
	var $fk_parent_line;
	var $desc;          	// Description ligne
	var $fk_product;		// Id produit predefini
	var $product_type = 0;	// Type 0 = product, 1 = Service

	var $qty;
	var $tva_tx;
	var $subprice;
	var $remise_percent;
	var $fk_remise_except;

	var $rang = 0;
	var $marge_tx;
	var $marque_tx;

	var $special_code;	// Liste d'options non cumulabels:
	// 1: frais de port
	// 2: ecotaxe
	// 3: ??

	var $info_bits = 0;	// Liste d'options cumulables:
	// Bit 0: 	0 si TVA normal - 1 si TVA NPR
	// Bit 1:	0 ligne normale - 1 si ligne de remise fixe

	var $total_ht;			// Total HT  de la ligne toute quantite et incluant la remise ligne
	var $total_tva;			// Total TVA  de la ligne toute quantite et incluant la remise ligne
	var $total_ttc;			// Total TTC de la ligne toute quantite et incluant la remise ligne

	// Ne plus utiliser
	var $remise;
	var $price;

	// From llx_product
	var $ref;						// Reference produit
	var $libelle;       // Label produit
	var $product_desc;  // Description produit

	var $localtax1_tx;
	var $localtax2_tx;
	var $total_localtax1;
	var $total_localtax2;

	var $skip_update_total; // Skip update price total for special lines

	/**
	 *      \brief     Constructeur d'objets ligne de propal
	 *      \param     DB      handler d'acces base de donnee
	 */
	function PropaleLigne($DB)
	{
		$this->db= $DB;
	}

	/**
	 *      \brief     Recupere l'objet ligne de propal
	 *      \param     rowid           id de la ligne de propal
	 */
	function fetch($rowid)
	{
		$sql = 'SELECT pd.rowid, pd.fk_propal, pd.fk_parent_line, pd.fk_product, pd.description, pd.price, pd.qty, pd.tva_tx,';
		$sql.= ' pd.remise, pd.remise_percent, pd.fk_remise_except, pd.subprice,';
		$sql.= ' pd.info_bits, pd.total_ht, pd.total_tva, pd.total_ttc, pd.marge_tx, pd.marque_tx, pd.special_code, pd.rang,';
		$sql.= ' p.ref as product_ref, p.label as product_libelle, p.description as product_desc';
		$sql.= ' FROM '.MAIN_DB_PREFIX.'propaldet as pd';
		$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'product as p ON pd.fk_product = p.rowid';
		$sql.= ' WHERE pd.rowid = '.$rowid;
		$result = $this->db->query($sql);
		if ($result)
		{
			$objp = $this->db->fetch_object($result);

			$this->rowid			= $objp->rowid;
			$this->fk_propal		= $objp->fk_propal;
			$this->fk_parent_line	= $objp->fk_parent_line;
			$this->desc				= $objp->description;
			$this->qty				= $objp->qty;
			$this->price			= $objp->price;		// deprecated
			$this->subprice			= $objp->subprice;
			$this->tva_tx			= $objp->tva_tx;
			$this->remise			= $objp->remise;
			$this->remise_percent	= $objp->remise_percent;
			$this->fk_remise_except = $objp->fk_remise_except;
			$this->fk_product		= $objp->fk_product;
			$this->info_bits		= $objp->info_bits;

			$this->total_ht			= $objp->total_ht;
			$this->total_tva		= $objp->total_tva;
			$this->total_ttc		= $objp->total_ttc;

			$this->marge_tx			= $objp->marge_tx;
			$this->marque_tx		= $objp->marque_tx;
			$this->special_code		= $objp->special_code;
			$this->rang				= $objp->rang;

			$this->ref				= $objp->product_ref;      // deprecated
            $this->product_ref		= $objp->product_ref;
            $this->libelle			= $objp->product_libelle;  // deprecated
            $this->product_label	= $objp->product_libelle;
			$this->product_desc		= $objp->product_desc;

			$this->db->free($result);
		}
		else
		{
			dol_print_error($this->db);
		}
	}

	/**
	 *      \brief     	Insert object line propal in database
	 *		\return		int		<0 if KO, >0 if OK
	 */
	function insert($notrigger=0)
	{
		global $conf,$langs,$user;

		dol_syslog("PropaleLigne::insert rang=".$this->rang);

		// Clean parameters
		if (empty($this->tva_tx)) $this->tva_tx=0;
		if (empty($this->localtax1_tx)) $this->localtax1_tx=0;
		if (empty($this->localtax2_tx)) $this->localtax2_tx=0;
		if (empty($this->total_localtax1)) $this->total_localtax1=0;
		if (empty($this->total_localtax2)) $this->total_localtax2=0;
		if (empty($this->rang)) $this->rang=0;
		if (empty($this->remise)) $this->remise=0;
		if (empty($this->remise_percent)) $this->remise_percent=0;
		if (empty($this->info_bits)) $this->info_bits=0;
		if (empty($this->special_code)) $this->special_code=0;
		if (empty($this->fk_parent_line)) $this->fk_parent_line=0;

		// Check parameters
		if ($this->product_type < 0) return -1;

		$this->db->begin();

		// Insert line into database
		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'propaldet';
		$sql.= ' (fk_propal, fk_parent_line, description, fk_product, product_type, fk_remise_except, qty, tva_tx, localtax1_tx, localtax2_tx,';
		$sql.= ' subprice, remise_percent, ';
		$sql.= ' info_bits, ';
		$sql.= ' total_ht, total_tva, total_localtax1, total_localtax2, total_ttc, special_code, rang, marge_tx, marque_tx)';
		$sql.= " VALUES (".$this->fk_propal.",";
		$sql.= " ".($this->fk_parent_line>0?"'".$this->fk_parent_line."'":"null").",";
		$sql.= " '".$this->db->escape($this->desc)."',";
		$sql.= " ".($this->fk_product?"'".$this->fk_product."'":"null").",";
		$sql.= " '".$this->product_type."',";
		$sql.= " ".($this->fk_remise_except?"'".$this->fk_remise_except."'":"null").",";
		$sql.= " ".price2num($this->qty).",";
		$sql.= " ".price2num($this->tva_tx).",";
		$sql.= " ".price2num($this->localtax1_tx).",";
		$sql.= " ".price2num($this->localtax2_tx).",";
		$sql.= " ".($this->subprice?price2num($this->subprice):'null').",";
		$sql.= " ".price2num($this->remise_percent).",";
		$sql.= " '".$this->info_bits."',";
		$sql.= " ".price2num($this->total_ht).",";
		$sql.= " ".price2num($this->total_tva).",";
		$sql.= " ".price2num($this->total_localtax1).",";
		$sql.= " ".price2num($this->total_localtax2).",";
		$sql.= " ".price2num($this->total_ttc).",";
		$sql.= ' '.$this->special_code.',';
		$sql.= ' '.$this->rang.',';
		if (isset($this->marge_tx)) $sql.= ' '.$this->marge_tx.',';
		else $sql.= ' null,';
		if (isset($this->marque_tx)) $sql.= ' '.$this->marque_tx;
		else $sql.= ' null';
		$sql.= ')';

		dol_syslog("PropaleLigne::insert sql=$sql");
		$resql=$this->db->query($sql);
		if ($resql)
		{
			$this->rang=$rangmax;

			$this->rowid=$this->db->last_insert_id(MAIN_DB_PREFIX.'propaldet');
			if (! $notrigger)
			{
				// Appel des triggers
				include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
				$interface=new Interfaces($this->db);
				$result = $interface->run_triggers('LINEPROPAL_INSERT',$this,$user,$langs,$conf);
				if ($result < 0) { $error++; $this->errors=$interface->errors; }
				// Fin appel triggers
			}

			$this->db->commit();
			return 1;
		}
		else
		{
			$this->error=$this->db->error()." sql=".$sql;
			dol_syslog("PropaleLigne::insert Error ".$this->error, LOG_ERR);
			$this->db->rollback();
			return -1;
		}
	}

	/**
	 * 	Delete line in database
	 *	@return	 int  <0 si ko, >0 si ok
	 */
	function delete()
	{
		global $conf,$langs,$user;

		$this->db->begin();

		$sql = "DELETE FROM ".MAIN_DB_PREFIX."propaldet WHERE rowid = ".$this->rowid;
		dol_syslog("PropaleLigne::delete sql=".$sql, LOG_DEBUG);
		if ($this->db->query($sql) )
		{
			// Appel des triggers
			include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
			$interface=new Interfaces($this->db);
			$result = $interface->run_triggers('LINEPROPAL_DELETE',$this,$user,$langs,$conf);
			if ($result < 0) { $error++; $this->errors=$interface->errors; }
			// Fin appel triggers

			$this->db->commit();

			return 1;
		}
		else
		{
			$this->error=$this->db->error()." sql=".$sql;
			dol_syslog("PropaleLigne::delete Error ".$this->error, LOG_ERR);
			$this->db->rollback();
			return -1;
		}
	}

	/**
	 *      \brief     	Mise a jour de l'objet ligne de propale en base
	 *		\return		int		<0 si ko, >0 si ok
	 */
	function update($notrigger=0)
	{
		global $conf,$langs,$user;

		// Clean parameters
		if (empty($this->tva_tx)) $this->tva_tx=0;
		if (empty($this->localtax1_tx)) $this->localtax1_tx=0;
		if (empty($this->localtax2_tx)) $this->localtax2_tx=0;
		if (empty($this->total_localtax1)) $this->total_localtax1=0;
		if (empty($this->total_localtax2)) $this->total_localtax2=0;
		if (empty($this->marque_tx)) $this->marque_tx=0;
		if (empty($this->marge_tx)) $this->marge_tx=0;
		if (empty($this->remise)) $this->remise=0;
		if (empty($this->remise_percent)) $this->remise_percent=0;
		if (empty($this->info_bits)) $this->info_bits=0;
		if (empty($this->special_code)) $this->special_code=0;
		if (empty($this->fk_parent_line)) $this->fk_parent_line=0;

		$this->db->begin();

		// Mise a jour ligne en base
		$sql = "UPDATE ".MAIN_DB_PREFIX."propaldet SET";
		$sql.= " description='".$this->db->escape($this->desc)."'";
		$sql.= " , tva_tx='".price2num($this->tva_tx)."'";
		$sql.= " , localtax1_tx=".price2num($this->localtax1_tx);
		$sql.= " , localtax2_tx=".price2num($this->localtax2_tx);
		$sql.= " , qty='".price2num($this->qty)."'";
		$sql.= " , subprice=".price2num($this->subprice)."";
		$sql.= " , remise_percent=".price2num($this->remise_percent)."";
		$sql.= " , price=".price2num($this->price)."";					// TODO A virer
		$sql.= " , remise=".price2num($this->remise)."";				// TODO A virer
		$sql.= " , info_bits='".$this->info_bits."'";
		if (empty($this->skip_update_total))
		{
			$sql.= " , total_ht=".price2num($this->total_ht)."";
			$sql.= " , total_tva=".price2num($this->total_tva)."";
			$sql.= " , total_ttc=".price2num($this->total_ttc)."";
		}
		$sql.= " , marge_tx='".$this->marge_tx."'";
		$sql.= " , marque_tx='".$this->marque_tx."'";
		$sql.= " , info_bits=".$this->info_bits;
		if (strlen($this->special_code)) $sql.= " , special_code=".$this->special_code;
		$sql.= " , fk_parent_line=".($this->fk_parent_line>0?$this->fk_parent_line:"null");
		$sql.= " WHERE rowid = ".$this->rowid;

		dol_syslog("PropaleLigne::update sql=$sql");
		$resql=$this->db->query($sql);
		if ($resql)
		{
			if (! $notrigger)
			{
				// Appel des triggers
				include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
				$interface=new Interfaces($this->db);
				$result = $interface->run_triggers('LINEPROPAL_UPDATE',$this,$user,$langs,$conf);
				if ($result < 0) { $error++; $this->errors=$interface->errors; }
				// Fin appel triggers
			}

			$this->db->commit();
			return 1;
		}
		else
		{
			$this->error=$this->db->error();
			dol_syslog("PropaleLigne::update Error ".$this->error, LOG_ERR);
			$this->db->rollback();
			return -2;
		}
	}

	/**
	 *      \brief     	Mise a jour en base des champs total_xxx de ligne
	 *		\remarks	Utilise par migration
	 *		\return		int		<0 si ko, >0 si ok
	 */
	function update_total()
	{
		$this->db->begin();

		// Mise a jour ligne en base
		$sql = "UPDATE ".MAIN_DB_PREFIX."propaldet SET";
		$sql.= " total_ht=".price2num($this->total_ht,'MT')."";
		$sql.= ",total_tva=".price2num($this->total_tva,'MT')."";
		$sql.= ",total_ttc=".price2num($this->total_ttc,'MT')."";
		$sql.= " WHERE rowid = ".$this->rowid;

		dol_syslog("PropaleLigne::update_total sql=$sql");

		$resql=$this->db->query($sql);
		if ($resql)
		{
			$this->db->commit();
			return 1;
		}
		else
		{
			$this->error=$this->db->error();
			dol_syslog("PropaleLigne::update_total Error ".$this->error, LOG_ERR);
			$this->db->rollback();
			return -2;
		}
	}

}

?>
