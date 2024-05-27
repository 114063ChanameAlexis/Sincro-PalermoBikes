<?php
/**
* 2007-2017 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Open Software License (OSL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/osl-3.0.php
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
* @author    Flexxus S.A <soporte@flexxus.com>
* @copyright 2007-2017 Flexxus SA
* @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
* International Registered Trademark & Property of PrestaShop SA
*/
class flxfn extends ObjectModel
{
    /*
    * Devuelve la ultima version disponible para actualizar el modulo
    *
    * @return true
    */
    public function getLastVersion()
    {
      /*// Set up a connection
      $conn = ftp_connect("190.210.181.235",21);

      // Login
      if (ftp_login($conn, "damian", "damian123"))
      {
          // Obtener los archivos contenidos en el directorio actual
          $contents = ftp_nlist($conn, "api_mitiendaonline_com/versiones/");
          foreach($contents as $key => $value):
						$data = explode("/",$value);
						$version['version'][] = $data[2];
					endforeach;
          // cerrar la conexión ftp

          ftp_close($conn);
          // Return the resource
          return $version;
      }*/
      return false;
    }

    public static function addonsRequest()
    {
        $path = DB::getInstance()->getValue('SELECT pathapi
            FROM '._DB_PREFIX_.'flx_version_erp
        WHERE idErp = '.Parametros::get('MS_TIPOFORM').'
            AND version = "'.Parametros::get('MS_VERSIONERP').'"');

        $protocol = 'http';
        $obj =  new flxsincro();        
        $version = explode('.', $obj->version);
        $end_point = 'apimto.mitiendaonline.com/erp/v'.$version[0].'.'.$version[1].'/'.$path;
        
        return Tools::file_get_contents($protocol.'://'.$end_point, false);
        
    }

    public static function getScript()
    {
        $content = self::addonsRequest();
        if($content == false)
            return false;
        $xml = simplexml_load_string($content, null, LIBXML_NOCDATA);
        return $xml;
    }

    public static function getCode()
    {
        $script = self::getScript();
        if($script == false)
            return false;
        $code = array();
        foreach($script as $c => $key)
        $code[] = array(
                    'name' => $key->name,
                    'script' => $key->codigo,
                    'tipo' => $key->tipo,
                    'posicion' => $key->posicion
                    );

        return $code;
    }

    public static function DBMSConnect($idErp,$host,$database,$usuario,$password)
    {
        $engine = Db::getInstance()->getValue('SELECT engine FROM '._DB_PREFIX_.'flx_erp WHERE idERP = '.$idErp);

        $ibase = new flxibase($engine,$host,$database,$usuario,$password);
        if($ibase->connect() == false)
        {
            return formulario::displayError('Error al conectar al servidor.Intente mas tarde nuevamente.');
        }else{
            Parametros::updateValues('MS_SERVIDOR',$host);
            Parametros::updateValues('MS_HOST',$database);
            Parametros::updateValues('MS_ENGINE',$engine);
            Parametros::updateValues('MS_USUARIO',$usuario);
            Parametros::updateValues('MS_PASSWORD',$password);

            if($engine == 1)
            {
              $result_version = $ibase->query("SELECT VERSION FROM VERSION ORDER BY VERSION DESC");

              if($result_version === false){
                    throw new Exception('VERSION DB');
              }

              $erp = $ibase->fetch_object($result_version);
              $MS_VERSION = Parametros::updateValues('MS_VERSIONERP',(float)$erp->VERSION);
              /*
              * En caso de ser Enterprise, validamos que la version no sea menor abs
              * la 3.06, en caso contrario devolveremos error de compatibilidad.
              **/
              if((int)Parametros::get('MS_TIPOFORM') == 1)
              {
                  if((float)$erp->VERSION >= (float)3.00 && (float)$erp->VERSION <= (float)3.99)
                    return formulario::displayConfirmation('Se conecto correctamente.');
                 else
                    return formulario::displayError('La versión de su ERP no es compatible. Contáctese con su proveedor.');
              }
              
              if((int)Parametros::get('MS_TIPOFORM') == 3)
              {
                  return formulario::displayConfirmation('Se conecto correctamente.');
              }

              $ibase->free_result($result_version);
            }
            else{
              Parametros::updateValues('MS_CONECT','enable');
              Parametros::updateValues('MS_INSTALL','1');
              return formulario::displayConfirmation('Se conecto correctamente.');
            }

        }

        $ibase->close();
    }

    /** Instala las vistas para la sincronizacion **/
    public static function installarScript(&$Mensaje)
    {
        $engine = Parametros::get('MS_ENGINE');
        $host = explode(';',Parametros::get('MS_SERVIDOR'));
        $database = Parametros::get('MS_HOST');
        $usuario = Parametros::get('MS_USUARIO');
        $password = Parametros::get('MS_PASSWORD');

        $ibase = new flxibase($engine,$host[0],$database,$usuario,$password);

        if($ibase->connect() == false)
        {
            $Mensaje = formulario::displayError('Error al conectar a la BD mientras se intentaba instalar las vistas.');
            return false;
        }
            
        $result = self::getCode();
        $error = array();
        
        if($result === false)
        {   
            $Mensaje = formulario::displayError('Error al obtener scripts para la Instalación.');
            return false;
        }
            

        foreach ($result as $script => $row)
        {
            $result_query = $ibase->query($row['script']);

            if($result_query === false)
              $error[] = $row['name'].' | Error: '.$ibase->getLastErrMsg();
            else{
              $ibase->free_result($result_query);
              $success[] = $row['name'];
            }
        }

        $ibase->close();
        foreach ($success as $suc)
                $html .= '<div class="alert-success"><i class="icon icon-code"></i> '.$suc.'</div>';

        foreach ($error as $err)
                $html .= '<div class="alert-danger"><i class="icon icon-code"></i> '.$err.'</div>';

        Parametros::updateValues('MS_CONECT','enable');
        Parametros::updateValues('MS_INSTALL','1');
        if(empty($error))
            $Mensaje = formulario::displayConfirmation('Se instalaron correctamente las siguientes vistas.'.$html);
        else
            $Mensaje = formulario::displayError('Error al instalar las siguientes vistas.'.$html);

        return (empty($error));
    }

    // funciones para el manejo de transacciones
    public static function startTransaction()
    {
        Db::getInstance()->execute("START TRANSACTION");
    }

    public static function breakTransaction($str = "")
    {
        if($str == "")
        return $msg = "Transaccion abortada debido al siguiente error: ".Db::getInstance()->getMsgError();
            else
        return $msg = "";
        Db::getInstance()->execute("ROLLBACK");
    }

    public static function commitTransaction($mensaje)
    {
        if($mensaje != '0'){
            echo($mensaje);
        }
        Db::getInstance()->execute("COMMIT");
    }

    //Funcion que devuelve el ID de Provincia
    public static function getProfile($profile,$id_lang)
    {
	     $id_profile = (int)Db::getInstance()->getValue('
                        SELECT `id_profile`
                        FROM `'._DB_PREFIX_.'profile_lang`
                        WHERE id_lang = '.$id_lang.' and `name` LIKE \''.pSQL($profile).'\'
                    ');
       return $id_profile;
	  }

    //funcion que devuelve las equivalencias de los estados para sincronziar los pedidos
    public static function getEstadosNP()
    {
        $sql = Db::getInstance()->executeS('SELECT * FROM '._DB_PREFIX_.'flx_estados_np');

        return $sql;

    }

    //funcion que devuelve el codigo vendedor
    public static function getSQLstock($USALOTE,$fecha,$MS_DEPOSITOS,$WHERE = '')
    {
      $sql_stock = '';
      if($USALOTE)
        $sql_stock = "SELECT S.id_articulo, Sum(S.npreservada) as NPReserva,
                             Sum(S.stockreal) as StockReal, Sum(S.stockremanente) as StockRemanente,
                             Sum(S.stockfactsinremitir) as StockFactSinRemitir
                        FROM MTO_STOCK('".$fecha."','".$MS_DEPOSITOS."') S ".
                        $WHERE.
                       " Group By S.id_articulo";
      else
        $sql_stock = "SELECT * FROM MTO_STOCK('".$fecha."','".$MS_DEPOSITOS."') ".$WHERE;

      return $sql_stock;
    }

    //funcion que devuelve los codigo ID_ERP de un pedido
    public static function getListIdERP($id_order)
    {
      $orderDetails = Db::getInstance()->executeS('SELECT DISTINCT p2p.ID_ERP
                                                        FROM `'._DB_PREFIX_.'order_detail` ord
                                                      INNER JOIN `'._DB_PREFIX_.'flx_p2p` p2p ON p2p.id_prestashop = ord.product_id
                                                    WHERE `id_order` = '.(int)$id_order);
      $ARTICULOS = '';
        foreach($orderDetails as $order => $detail):
          $ARTICULOS .= "'".$detail['ID_ERP']."',";
        endforeach;
        $ARTICULOS = substr($ARTICULOS, 0, -1);
      return $ARTICULOS;
    }

    //funcion que devuelve los codigo ID_ERP de un pedido
    public static function getIdPricePromotion($params)
    {
        $result = (int)Db::getInstance()->getValue("SELECT id_specific_price 
                                                       FROM "._DB_PREFIX_."specific_price 
                                                WHERE id_product = ".$params." 
                                                  AND id_group = 0 
                                                  AND reduction_type = 'amount'");
        return $result;
    }

    //funcion que devuelve el codigo vendedor
    public static function getVendedor($codCliente)
    {
        $idEquivalencia = Db::getInstance()->getValue('SELECT CODVENDEDOR
                                                          FROM '._DB_PREFIX_.'flx_cliente
                                                     WHERE ID_ERP LIKE "'.pSQL($codCliente).'"');
        return $idEquivalencia;
    }

    //actualiza los estados de los articulso segun el stock positivo o negativo
    public static function updateActive($id_shop,$bool,$imagen=false)
    {

        Db::getInstance()->execute('UPDATE '._DB_PREFIX_.'product_shop ps 
                                    INNER JOIN '._DB_PREFIX_.'flx_p2p p2p on p2p.id_prestashop = ps.id_product
                                    set ps.active = 0
                                    WHERE p2p.muestraweb = 0');
        if($bool)
        {
            if($imagen){
                return Db::getInstance()->execute('UPDATE '._DB_PREFIX_.'product_shop ps
                    INNER JOIN '._DB_PREFIX_.'product p ON ps.id_product = p.id_product
                    INNER JOIN '._DB_PREFIX_.'stock_available st ON st.id_product = ps.id_product and st.id_shop = ps.id_shop
                    INNER JOIN '._DB_PREFIX_.'flx_p2p p2p ON p2p.id_prestashop = p.id_product
                    INNER JOIN '._DB_PREFIX_.'image i ON i.id_product = p.id_product and i.cover = 1
                    SET ps.active = 1, p.active = 1
                    WHERE st.id_shop = '.$id_shop.' and p2p.muestraweb = 1 and i.id_image > 0 and st.quantity > 0');
            }else{
                return Db::getInstance()->execute('UPDATE '._DB_PREFIX_.'product_shop ps
                    INNER JOIN '._DB_PREFIX_.'product p ON ps.id_product = p.id_product
                    INNER JOIN '._DB_PREFIX_.'stock_available st ON st.id_product = ps.id_product and st.id_shop = ps.id_shop
                    INNER JOIN '._DB_PREFIX_.'flx_p2p p2p ON p2p.id_prestashop = p.id_product
                    SET ps.active = 1, p.active = 1
                    WHERE st.id_shop = '.$id_shop.' and p2p.muestraweb = 1 and st.quantity > 0');
            }
        } else {
           return Db::getInstance()->execute('UPDATE '._DB_PREFIX_.'product_shop ps
                                                INNER JOIN '._DB_PREFIX_.'product p ON ps.id_product = p.id_product
                                                INNER JOIN '._DB_PREFIX_.'stock_available st ON st.id_product = ps.id_product and st.id_shop = ps.id_shop
                                                INNER JOIN '._DB_PREFIX_.'flx_p2p p2p ON p2p.id_prestashop = p.id_product
                                                SET ps.active = 0, p.active =0
                                                WHERE st.id_shop = '.$id_shop.' AND st.id_product_attribute = 0 AND st.quantity <= 0');
         }
    }

    //traigo los pedidos de la tienda que no se sincronizaron
    public static function getLastOrder($transportista, $listaPrecio, $tipoOperacion, $depositos, $mayorista, $id_shop)
    {
        $sql = "SELECT DISTINCT ORD.reference AS CodigoPedido,CUS.id_customer AS CodigoCliente,ORD.date_add AS FechaComprobante,
                                    CONCAT('Cod. Cliente E-Commerce:',CUS.id_customer,' ','Nro Pedido E-Commerce: ',ORD.id_order,' ',
                                    ' ','Codigo Referencia: ',ORD.reference,' ',
                                      'Descuento: ',(CASE WHEN ORD.total_discounts <> 0.00 THEN 'SI' ELSE 'NO' END),' ','Forma de Pago: ', ORD.payment,' ID de transaccion: ', (CASE
                                    WHEN ORDP.payment_method LIKE 'Mercado%pago%' THEN ORDP.transaction_id
                                    WHEN ORDP.payment_method LIKE 'Todopago%' THEN ORDP.transaction_id
                                    ELSE '' END),'Transporte: ',CA.name,' Direccion De Entrega: ',ADR.address1) AS Observaciones,
                                    CONCAT ('Nombre: ', CUS.firstname,' ',CUS.lastname,' - Email: ',CUS.email,' - Direccion: ',ADR.address1,
                                            ' - CP: ',ADR.postcode,' - Localidad: ',ADR.city,' - Phone: ',ADR.phone) AS DatosCliente,
                                    '".$transportista."'AS CodigoTransporte,
                                    '".$listaPrecio."' AS ListaPrecio,
                                    '".$tipoOperacion."' AS TipoOperacion,
                                    (CASE
                                    WHEN CA.name LIKE 'Oca%' THEN (ORD.total_shipping_tax_excl / 1.21)
                                    WHEN CA.name LIKE 'Andreani%' THEN (ORD.total_shipping_tax_excl / 1.21)
                                    WHEN CA.name LIKE 'MercadoEnvios%' THEN (ORD.total_shipping_tax_excl / 1.21)
                                    WHEN CA.name LIKE 'Mercado Libre%' THEN (ORD.total_shipping_tax_excl / 1.21)
                                    WHEN CA.name LIKE 'Normal a domicilio%' THEN (ORD.total_shipping_tax_excl / 1.21)
                                    WHEN CA.name LIKE 'Moto Express a domicilio%' THEN (ORD.total_shipping_tax_excl / 1.21)
                                    ELSE ORD.total_shipping_tax_excl END) as montoEnvio,
                                    ORD.id_order AS ordenTienda,
                                    '' as DESCUENTOGRAL,
                                    ORD.id_customer
                            FROM "._DB_PREFIX_."orders AS ORD
                                 INNER JOIN "._DB_PREFIX_."order_detail AS ORDT ON (ORD.id_order = ORDT.id_order)
                                 LEFT JOIN "._DB_PREFIX_."order_payment AS ORDP ON (ORD.reference = ORDP.order_reference)
                                 AND ((ORDP.transaction_id <> '' and ORDP.payment_method LIKE 'Mercado%Pago%') OR 
                                     (ORDP.payment_method NOT LIKE 'Mercado%Pago%')) AND ((ORDP.transaction_id <> '' and ORDP.payment_method LIKE 'Todo%Pago%') OR 
                                     (ORDP.payment_method NOT LIKE 'Todo%Pago%'))
                                 INNER JOIN "._DB_PREFIX_."customer AS CUS ON (ORD.id_customer = CUS.id_customer)
                                 INNER JOIN "._DB_PREFIX_."carrier AS CA ON(ORD.id_carrier = CA.id_carrier)
                                 INNER JOIN "._DB_PREFIX_."address AS ADR ON (ORD.id_address_delivery = ADR.id_address)
                                 
                            WHERE ORD.current_state IN (".$depositos.")
                              AND ORD.id_shop = (".$id_shop.")
                              AND NOT EXISTS (SELECT id_prestashop FROM "._DB_PREFIX_."flx_pedido mfp where mfp.id_prestashop = ORD.id_order)";
                            //AND (SELECT count(*) FROM "._DB_PREFIX_."flx_p2p flx2 where flx2.id_prestashop = ORDT.product_id ) = (Select Count(*) From "._DB_PREFIX_."order_detail AS ORDT2 Where (ORD.id_order = ORDT2.id_order))
        
                        //return $sql;
        $result = Db::getInstance()->executeS($sql);

        if(empty($result))
                return false;
            else
                return $result;
    }
    public static function getMensajeOrden($id_order){
      $sql = "SELECT MEN.message
              FROM "._DB_PREFIX_."message AS MEN
              WHERE MEN.message NOT LIKE 'Pedido m%' 
              AND MEN.id_order = ".$id_order;

      return Db::getInstance()->getValue($sql);        
    }
    public static function getBonifEscalonada($escalonado, $porcentaje)
    {
        $resultado = ((100 - $escalonado) / 100) * ((100 - $porcentaje) / 100);
        $resultado = (1 - $resultado) * 100;
        return $resultado;
    }

    public static function getRuleDiscount($id_order, $type = 'porcentaje')
    {
        $DTOGENERAL = 0;
        $order = Db::getInstance()->executeS("SELECT O.total_discounts_tax_excl, O.total_paid_tax_excl, O.total_products, OC.shipping_cost_tax_excl
                                                    FROM "._DB_PREFIX_."orders O
                                                LEFT JOIN "._DB_PREFIX_."order_carrier OC ON OC.id_order = O.id_order
                                              WHERE O.id_order = ".$id_order);
        $MOUNT = (float)$order[0]['total_products'] + (float)$order[0]['shipping_cost_tax_excl'];
        $DESCOUNT = ((float)$order[0]['total_paid_tax_excl'] / $MOUNT);
        $DTOGENERAL = ((float)$order[0]['total_discounts_tax_excl'] > 0.00 ? (float)((1 - $DESCOUNT) * 100) : 0.00);

        return round($DTOGENERAL, 2);
    } 

    //Traemos el mensaje del cliente de un pedido
    public static function getMenssage($id_order)
    {
      $sql = "SELECT CUSMG.message
                  FROM "._DB_PREFIX_."customer_thread CUSTH
                INNER JOIN "._DB_PREFIX_."customer_message CUSMG ON CUSMG.id_customer_thread = CUSTH.id_customer_thread
              WHERE CUSTH.id_order = ".$id_order."
              ORDER BY CUSTH.id_customer_thread,CUSMG.id_customer_message ASC";

      return Db::getInstance()->getValue($sql);

    }

    //devuelve datos del erp
    public static function getDateErp($getData = null,$parametro = null,$type = true)
    {

        $sincro = new sincro();
        if(!$sincro->isConnect())
          return '';

        switch ($getData){
            case 'getMonedas':
                return $sincro->getMonedas();
            break;
            case 'getDepositos':
                return $sincro->getDepositos($parametro);
            break;
            case 'getDepositosStock':
                return $sincro->getDepositosStock($parametro);
            break;
            case 'getUsuarios':
                return $sincro->getVendedores();
            break;
            case 'getCobrador':
                return $sincro->getCobradores();
            break;
            case 'getActividades':
                return $sincro->getActividades();
            break;
            case 'getZonas':
                return $sincro->getZonas();
            break;
            case 'getOperaciones':
                return $sincro->getOperaciones();
            break;
            case 'getLocalidades':
                return $sincro->getLocalidades($parametro);
            break;
            case 'getMultiplazo':
                return $sincro->getMultiplazo();
            break;
            case 'getFlete':
                return $sincro->getFlete($parametro,$type);
            break;
            case 'getMonedasPedido':
                return $sincro->getMonedasPedido();
            break;
            default:
                return Tools::jsonEncode($json);
            break;
        }


    }

    // funcion que devuelve las equivalencias de ID
    public static function equivalenciaID($ID,$tabla,$id_prestashop = true)
    {

        if($id_prestashop)
        {
            $idEquivalencia = Db::getInstance()->getValue('SELECT id_prestashop
                                                            FROM '._DB_PREFIX_.$tabla.'
                                                            WHERE ID_ERP = "'.pSQL($ID).'"');
        }else{
            $idEquivalencia = Db::getInstance()->getValue('SELECT ID_ERP
                                                            FROM '._DB_PREFIX_.$tabla.'
                                                            WHERE id_prestashop = '.(int)$ID);
        }

        if(empty($idEquivalencia))
                return "NULL";
            else
                return $idEquivalencia;

    }

    /*
    * Funcion que busca si existe alguna Regla de Catalogo
    * Según el id del cliente.
    *
    * @param  $idCliente integer
    * @return $idCartRule integer
    */
    public static function getIdBonification($params)
    {
        if(!$params['restriction']) {
            $id = (int)Db::getInstance()->getValue('SELECT id_cart_rule 
                                                        FROM '._DB_PREFIX_.'cart_rule
                                                    WHERE id_customer = '.$params['id_customer']);
        } else {
            $id = (int)Db::getInstance()->getValue('SELECT id_cart_rule 
                                                        FROM '._DB_PREFIX_.'cart_rule
                                                    WHERE id_customer = '.$params['id_customer'].' 
                                                      AND product_restriction = 1');
        }

        return $id;
    }

    public static function deletCartRule($params)
    {
        $sql = "DELETE "._DB_PREFIX_."cart_rule cr 
          Inner Join "._DB_PREFIX_."cart_rule_lang crl ON crl.id_cart_rule = cr.id_cart_rule 
          Inner Join "._DB_PREFIX_."cart_rule_country crc ON crc.id_cart_rule = cr.id_cart_rule 
          Inner Join "._DB_PREFIX_."cart_rule_carrier crca ON crca.id_cart_rule = cr.id_cart_rule
          Inner Join "._DB_PREFIX_."cart_rule_group crg ON crg.id_cart_rule = cr.id_cart_rule
          Inner Join "._DB_PREFIX_."cart_rule_shop crs ON crs.id_cart_rule = cr.id_cart_rule
          Inner Join "._DB_PREFIX_."cart_rule_combination crco ON ((crco.id_cart_rule_1 = cr.id_cart_rule) OR (crco.id_cart_rule_2 = cr.id_cart_rule))
          Inner Join "._DB_PREFIX_."cart_rule_product_rule_group crp ON crp.id_cart_rule = cr.id_cart_rule
          Inner Join "._DB_PREFIX_."cart_rule_product_rule crpr ON crpr.id_product_rule_group = crp.id_product_rule_group
          Inner Join "._DB_PREFIX_."cart_rule_product_rule_value crprv ON crprv.id_product_rule_group = crp.id_product_rule_group
        WHERE cr.id_customer = ".$params['id_customer']." 
          AND cr.product_restriction = 1";

        return (bool)Db::getInstance()->execute($sql);
    }

    //funcion para eliminar equivalenciaID
    public static function deleteEq($id,$specific_price,$tabla)
    {
      $sql = 'DELETE FROM '._DB_PREFIX_.$tabla.' WHERE id_prestashop = '.$specific_price.' and id_producto = '.$id;
      $result = Db::getInstance()->executeS($sql);
    }

    /*
  	* Calculamos el precio del producto con el monto interno
  	*
  	* @param float $precio precio base de cada articulo
  	* @param float $iva coeficiente de cada articulo
  	* @param float $montoII monto impuesto incluido
  	* @param float $porcentajeII porcentaje de impuesto incluido
  	*
  	* @return float $precioII
  	*/
  	public static function getPrecioII($precio,$montoII,$porcentajeII)
  	{
      if((float)$porcentajeII > 0.00)
    		$impInterno = $precio * ($porcentajeII  / 100);
      else
        $impInterno = $montoII;

      return (float)round($impInterno,2);
  	}


    //Funcion que devuelve el ID de COUNTRY
    public static function getIdCountryByName()
    {
        $id_country = Parametros::get('MS_COUNTRYS');
        $result = Db::getInstance()->executeS('
                        SELECT c.`id_country`,cl.`name`
                          FROM `'._DB_PREFIX_.'country` c
                         LEFT JOIN `'._DB_PREFIX_.'country_lang` cl ON (c.`id_country` = cl.`id_country` and cl.`id_lang` = 1)
                        WHERE c.`id_country` in ('.pSQL($id_country).')');
       return $result;
    }

    public static function getProvincias($id_country, $id_erp = '',$erp = null)
    {
        if($erp != null):
        $sql='SELECT p.ID_ERP,p.name FROM '._DB_PREFIX_.'state s
              INNER JOIN '._DB_PREFIX_.'flx_provincia p ON (s.id_state = p.id_prestashop)
                  WHERE p.modificar=1 and s.id_country in ('.$id_country.')';
        $result = Db::getInstance()->executeS($sql);
        $i = 0;
            foreach($result as $value):
                  $data[$i]['Codigo'] = $value['ID_ERP'];
                  $data[$i]['Nombre'] = $value['name'];
                  $i++;
            endforeach;
          return $data;
        endif;

        if($id_erp == ''):
          $sql='SELECT s.id_state,s.name FROM '._DB_PREFIX_.'state s
                  WHERE s.id_country in ('.$id_country.')';

          $result = Db::getInstance()->executeS($sql);
          return $result;
        else:
            $sql='SELECT s.id_state,s.name FROM '._DB_PREFIX_.'state s
                    INNER JOIN '._DB_PREFIX_.'flx_provincia p ON (s.id_state = p.id_prestashop)
                    WHERE p.ID_ERP ='.$id_erp;

            $result = Db::getInstance()->getRow($sql);
            return $result;

        endif;

    }

    public static function getLocalidades($id_erp = '',$erp = null)
    {

    }

    //funcion que devuelve la condicion de un cliente
    public static function equivalenciaCondicionIva($id_cliente)
    {
        $sql = 'SELECT CODIGOIVA FROM '._DB_PREFIX_.'flx_cliente
                WHERE id_prestashop = '.$id_cliente;
        $result = Db::getInstance()->getValue($sql);
        if(empty($result))
                return false;
            else
                return $result;
    }

    //traigo el detalle de cada pedido
    public static function getOrderTalles($talle,$idPedido){

        //echo 'talle: '.$talle.' id_pedido: '.$idPedido;

        $sql = "SELECT DISTINCT  OD.id_order AS CodigoPedido,'1' AS Linea,FLX.ID_ERP AS CodigoArticulo,
                                    OD.product_quantity AS Cantidad,OTAX.total_amount AS MONTOIVA1,
                                    O.id_customer as CLIENTE,FLX.reference as CodigoProducto,
                                    FLX.id_prestashop as CodigoPresta,".($talle == 1 ? "tl.name as Talle,": "")."
                                    OD.product_price AS price_original,
                                    OD.unit_price_tax_excl AS PrecioUnitario,
                                    OD.reduction_percent AS Descuento,
                                    OD.reduction_amount AS DescuentoM,
                                    OD.ecotax AS montoii 
                                        FROM "._DB_PREFIX_."orders AS O
                                        INNER JOIN "._DB_PREFIX_."order_detail AS OD ON (OD.id_order = O.id_order)
                                        LEFT JOIN "._DB_PREFIX_."order_detail_tax AS OTAX ON (OTAX.id_order_detail = OD.id_order_detail )".
                                        ($talle == 1 ? " LEFT JOIN "._DB_PREFIX_."product_attribute_combination AS ptc ON(ptc.id_product_attribute = OD.product_attribute_id)
                                                         LEFT JOIN "._DB_PREFIX_."attribute_lang AS tl ON(tl.id_attribute=ptc.id_attribute)" : "")."
                                        INNER JOIN "._DB_PREFIX_."flx_p2p AS FLX ON (FLX.id_prestashop = OD.product_id)
                                    WHERE O.id_order = ".$idPedido;

        //echo '<br>'.$sql.'<br>';                           
                //return $sql;
        $result = Db::getInstance()->executeS($sql);

        if(empty($result))
                return false;
            else
                return $result;
    }

    //Funcion que devuelve el ID de Provincia
    public static function getIdByName($state)
    {
        $id_state=(int)Db::getInstance()->getValue('
                        SELECT `id_prestashop`
                        FROM `'._DB_PREFIX_.'flx_provincia`
                        WHERE `ID_ERP` LIKE \''.pSQL($state).'\'
                    ');
       return $id_state;

    }

    //Funcion que devuelve el ID del atributo talle
    public static function getAttributesGroupsID($id_lang,$attribute_name)
    {

        return Db::getInstance()->getValue('
                SELECT agl.id_attribute_group
                    FROM `'._DB_PREFIX_.'attribute_group` ag
                        LEFT JOIN `'._DB_PREFIX_.'attribute_group_lang` agl
                    ON (ag.id_attribute_group = agl.id_attribute_group)
                WHERE agl.name LIKE \''.pSQL($attribute_name).'\' AND agl.id_lang = '.(int)$id_lang.''
                );
    }
    //Funcion que devuelve el ID de la Caracteristica FAMILIA
    public static function getFeatureID($id_lang,$feature_name)
    {

        return Db::getInstance()->getValue('
                SELECT fl.id_feature
                    FROM `'._DB_PREFIX_.'feature_lang` fl
                    WHERE fl.name LIKE \''.pSQL($feature_name).'\' AND fl.id_lang = '.(int)$id_lang.''
                );
    }

    //funcion que devuelve las equivalencias de LISTAS
    public static function equivalenciaListaPedido($id_cliente)
    {
        $id_lang = Configuration::get('PS_LANG_DEFAULT');
        $id_lista = Db::getInstance()->getValue('SELECT gl.name FROM '._DB_PREFIX_.'customer c
                                                INNER JOIN '._DB_PREFIX_.'group_lang gl ON(c.id_default_group = gl.id_group AND gl.id_lang = '.$id_lang.')
                                                WHERE c.id_customer = '.$id_cliente);
        switch($id_lista) {
            case 'Cliente':
                return 1;
            case 'Lista 01':
                return 1;
            break;
            case 'Lista 02':
                return 2;
            break;
            case 'Lista 03':
                return 3;
            break;
            case 'Lista 04':
                return 4;
            break;
            case 'Lista 05':
                return 5;
            break;
            default:
                return 1;
            break;
        }
    }

    //funcion que devuelve el precio con el descuento incluido
    public static function getPriceReduction($id_product,$price){
        $tax = Db::getInstance()->getValue('SELECT t.rate FROM '._DB_PREFIX_.'product p
                                                        JOIN '._DB_PREFIX_.'tax_rule tr ON(tr.id_tax_rules_group = p.id_tax_rules_group)
                                                        JOIN '._DB_PREFIX_.'tax t ON(t.id_tax = tr.id_tax)
                                            WHERE p.id_product='.$id_product);
        $price= $price * (($tax/100)+1);
        return $price;
    }

    //Funcion que retorna todos los clientes cargados desde PS
    public static function getCustomers($fechaSincronizacion)
    {
        $MS_LOCSNA = (int)Parametros::get('MS_RADIOLOCSNA');

        $sql = "SELECT CONCAT(CUS.lastname,' ',CUS.firstname) as razon_social,
                              ADR.company AS EMPRESA,
                              (CASE WHEN ADR.address1 = '' then 'S/N Domicilio' else ADR.address1 END) AS DIRECCION,
                              ADR.vat_number AS CUIT,
                              ADR.dni AS DNI,
                              ADR.postcode AS CP,
                              CUS.id_customer, 
                              (CASE WHEN ADR.phone = '' then '00000000' else ADR.phone END) as Telefono,
                              ADR.phone_mobile as Celular,
                              CUS.email as Email,
                              CONCAT ('Cod Cliente E-commerce: ',CUS.id_customer,".($MS_LOCSNA ? "' - '" : "' - Localidad:' ,ADR.city, ' - '")." ,ADR.other) AS Comentarios,
                              (CASE when ADR.id_state = 0 then 104 else ADR.id_state END) AS CODIGOPROVINCIA,
                              (CASE WHEN ADR.city = '' and CUS.EMAIL LIKE '%@masimple.com' then 'S/N localidad' else ADR.city END) AS LOCALIDAD
                        FROM "._DB_PREFIX_."customer as CUS
                        INNER JOIN "._DB_PREFIX_."address as ADR ON (CUS.id_customer = ADR.id_customer)
                                       and ADR.id_address = (Select Min(A.id_address)
                                                               From "._DB_PREFIX_."address A
                                                              Where A.id_customer = CUS.id_customer 
                                                              and ((A.id_state = 0 and CUS.email LIKE '%@masimple.com') or (A.id_state <> 0))
                                                                and A.deleted = 0)
                WHERE CUS.email <> 'noreply@prestashop.com'
                and CUS.deleted <> 1 
                and CUS.date_add > ".date('Y-m-d',strtotime($fechaSincronizacion))." 
                and not exists (Select * from "._DB_PREFIX_."flx_cliente erp 
                                 WHERE CUS.id_customer = erp.id_prestashop)
                and exists (Select id_customer from "._DB_PREFIX_."orders where id_customer = CUS.id_customer)";
//   echo "sql clientes --> ".$sql."<br>";
         return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
    }

    
    //Obtener ID de LISTA 01
    public static function getIDLista()
    {
		$l = [];
        $id_lista = Db::getInstance()->executeS('SELECT gl.name,gl.id_group FROM '._DB_PREFIX_.'group_lang gl
                                                    WHERE gl.id_lang = '.(int)Configuration::get('PS_LANG_DEFAULT').' AND gl.name LIKE "Lista %"');
        foreach($id_lista as $lista => $idLista){
          $l[$idLista['name']] = $idLista['id_group'];
        }
        return $l;
    }

    //Obtenemos Todos los grupos Creados
    public static function getGroups($id_lang, $id_shop = false)
	{
		
        $shop_criteria = '';
		if ($id_shop)
			$shop_criteria = Shop::addSqlAssociation('group', 'g');

        $groups = Db::getInstance()->executeS('
		SELECT DISTINCT g.`id_group`, g.`reduction`, g.`price_display_method`, gl.`name`
		FROM `'._DB_PREFIX_.'group` g
		LEFT JOIN `'._DB_PREFIX_.'group_lang` AS gl ON (g.`id_group` = gl.`id_group` AND gl.`id_lang` = '.(int)$id_lang.')
		'.$shop_criteria.'
		ORDER BY g.`id_group` ASC');
        
        foreach($groups as $g => $k)
        {
            $group[] = $k['id_group'];
        }

		return $group;
	}

    //Obtener Listado de impuestos
    public static function getTaxList()
    {
		$l = [];
        $id_tax = Db::getInstance()->executeS('SELECT tx.id_tax_rules_group,t.rate
                                                    FROM '._DB_PREFIX_.'tax t
                                                 INNER JOIN '._DB_PREFIX_.'tax_rule tx ON tx.id_tax = t.id_tax
                                               WHERE t.active = 1');
        foreach($id_tax as $tax => $iva){
          $l[$iva['id_tax_rules_group']] = $iva['rate'];
        }
        return $l;
    }

    // funcion que devuelve las equivalencias de LISTAS
    public static function equivalenciaLista($idErp, $id_producto, $id_shop, $tabla)
    {

        $idEquivalencia = Db::getInstance()->getValue('SELECT id_prestashop FROM '._DB_PREFIX_.$tabla.'
                                                            WHERE ID_ERP = '.$idErp.'
                                                                  AND id_producto = '.$id_producto.'
                                                                  AND id_shop = '.$id_shop);

        return (int)$idEquivalencia;

    }

    //funcion para traer las categorias padres
    public static function categoriaPadre($parent)
    {

        $categories = Db::getInstance()->executeS('
        SELECT c.`id_category`, c.`id_parent`
                FROM `'._DB_PREFIX_.'category` c
            WHERE c.id_category="'.$parent.'"');

        $list = array();
        foreach ($categories as $cat)
        {
              $list[] = $cat['id_category'];
              $list = array_merge($list, self::categoriaPadre($cat['id_parent']));
        }

        return $list;
    }

    /*
    * Funcion para devolver true o false
    * devolvera verdadero en caso que los padres esten activos
    * y devolvera falso en caso de que no esten publicados.
    *
    **/
    public static function categoryParentStatus($parent)
    {

        if($parent == 2)
            return true;

        $categories = Db::getInstance()->executeS('
        SELECT distinct c.`id_category`, c.`id_parent`, c.`active` 
                FROM `'._DB_PREFIX_.'category` c
            WHERE c.id_category="'.$parent.'"');

        foreach ($categories as $cat)
        {
              if($cat['active'])
                return self::categoryParentStatus($cat['id_parent']);
              else
                return false;
        }
      
    }

    //Funcion preformatea TEL && CEL
    public static function phoneFormat($phone,$type)
    {
        $phone = trim($phone);
        $phone = str_replace(
            array("\\", "¨", "º", "~",
               "#", "@", "|", "!", "\"",
               "·", "$", "%", "&", "/",
               "(", ")", "?", "'", "¡",
               "¿", "[", "^", "`", "]",
               "+", "}", "{", "¨", "´",
               ">", "< ", ";", ",",
               ".", " "),
        "",$phone
        );
        $telefono = '';
        $telefonos = explode('-',$phone);

        if($type == 'TEL' && $telefonos != '')
        $telefono = str_replace(array("TEL:","tel:","Tel:"),"",$telefonos[0]);

        if($type == 'CEL' && count($telefonos) > 1)
        $telefono = str_replace(array("CEL:","cel:","Cel:"),"",$telefonos[1]);

        return $telefono;
    }

    public static function updateFechaUltimaSincro($tabla, $fechainiciosincro,$id_shop)
    {
        $FechaSincro = self::getFechaUltimaSincro($tabla,$id_shop);
        $fechainiciosincro = date('Y-m-d '.'H:i', strtotime($fechainiciosincro));
        if(empty($FechaSincro))
          Db::getInstance()->execute(
            'INSERT INTO '._DB_PREFIX_.'flx_sincro
                         (objetosincro, id_shop, FechaUltimaSincro)
                  VALUES ("'.pSQL($tabla).'","'.$id_shop.'","'.$fechainiciosincro.'")
            ON DUPLICATE KEY UPDATE FechaUltimaSincro="'.$fechainiciosincro.'"');
        else
          Db::getInstance()->execute(
            'UPDATE '._DB_PREFIX_.'flx_sincro
                SET FechaUltimaSincro="'.$fechainiciosincro.'"
              WHERE objetosincro = "'.pSQL($tabla).'"
                AND id_shop = '.$id_shop);
    }

    // funcion para obtener la fecha de ultima sincro segun la tabla/vista que se esta sincronizando
    public static function getFechaUltimaSincro($tabla,$id_shop)
    {
        $fechaultimasincro = Db::getInstance()->getValue('SELECT FechaUltimaSincro
                                                            FROM '._DB_PREFIX_.'flx_sincro
                                                           WHERE objetosincro = "'.pSQL($tabla).'"
                                                             AND objetosincro <> "MTO_DESCUENTOTOTAL"
                                                             AND id_shop = '.$id_shop);

        return $fechaultimasincro;
    }

    public static function getUltimaSincroGeneral()
    {
        $fechaultimasincro = Db::getInstance()->getValue('SELECT Min(FechaUltimaSincro) as fechaultimasincro
                                                            FROM '._DB_PREFIX_.'flx_sincro
                                                           WHERE objetosincro <> "MTO_DESCUENTOTOTAL" 
                                                           AND id_shop = '.Context::getContext()->shop->id);

        if(empty($fechaultimasincro) || $fechaultimasincro == '0000-00-00 00:00:00')
          return "1900-01-01 00:00:00";
        else
          return $fechaultimasincro;

    }
    //funcion para traer la ultima fecha de actualización de cada uno de los elementos sincronizados
    public static function ultimaFechaSincro($tabla, $id_shop, $where = false )
    {
        //$db=Db::getInstance();
        $ultimaFechaSincro = self::getFechaUltimaSincro($tabla,$id_shop);

        if(empty($ultimaFechaSincro) || $ultimaFechaSincro == '0000-00-00 00:00:00')
          $ultimaFechaSincro = "1900-01-01 00:00:00";

        if(!$where)
            return $ultimaFechaSincro;
        else
            return " WHERE FECHAMODIFICACION >= '".$ultimaFechaSincro."'";
    }

    //Funcion que devuelve el ID de la lista
    public static function listaDefault($Lista)
    {

        $sql ='SELECT g.id_group FROM `'._DB_PREFIX_.'group` g
                                 LEFT JOIN `'._DB_PREFIX_.'group_lang` gl ON (g.`id_group` = gl.`id_group`)
            WHERE `name` LIKE \''.pSQL($Lista).'\'';

        $result = Db::getInstance()->getValue($sql);

        if(empty($result))
            return 3;
        else
            return (int)$result;

    }

    //funcion letraCapital
    public static function letraCapital($name)
    {
        $string = ucwords(strtolower($name));
        return $string;
    }

    //funcion para escapar strings
    public static function escape($str)
    {
            $search=array("\\","\0","\n","\r","\x1a","'",'"','á','Á', 'é', 'É', 'í', 'Í', 'ó', 'Ó', 'ú', 'Ú');
            $replace=array("\\\\","\\0","\\n","\\r","\Z","",'\"','a', 'A', 'e', 'E', 'i', 'I', 'o', 'O', 'u', 'U');
            return str_replace($search,$replace,$str);
    }

    //Funcion para obtener un string valido
    private static function getValidstr($string, $sbstr = 0, $capital = false )
    {
        //Conversion segura utf8
        $string = trim($string);
        
        if(!empty($string))
        {
            $string = mb_convert_encoding( $string, 'UTF-8', 'windows-1252');
           
            // Letra capital
            $string = ($capital ? ucwords(mb_strtolower($string, 'UTF-8') ) : $string);
            
            //Cortamos caracteres si sbstr es mayor a cero
            if($sbstr > 0)
            $string = substr($string,0,$sbstr);
        }        

        return $string;
    }

    public static function getValidestrName($string,$sbstr = 0, $capital = false)
    {
        $string = self::getValidstr($string,$sbstr, $capital);

        $string = str_replace(
            array("#",  "[", "]", "}", "{", ">", "<","=","",";","`","°","'"),
            '',
            $string
        );

        $string = str_replace(
            array("'"),
            '´',
            $string
        );
        return trim($string);
    }

    public static function getLinkRewrite($string)
    {
        $string = self::getValidestrName($string);
        //Esta parte se encarga de eliminar cualquier caracter extraño
        $string = str_replace(' ', '-', $string);
        $string = str_replace('?', '-', $string);
        $string = str_replace('`', '', $string);
        $string = str_replace('(', '', $string);
        $string = str_replace(')', '', $string);
        $string = str_replace('//', '', $string);
        $string = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $string);
        return $string;
    }

	public static function getValidestrDescription($string,$sbstr = 0, $capital = false)
    {
        $string = self::getValidstr($string,$sbstr, $capital);

        return $string;
    }

    //Funcion para limpiar caracteres especiales.
    public static function sanear_string($string,$esTag = 0,$capital = false,$sbstr = 0)
    {
        $string = trim($string);
        $string = str_replace(
            array('á', 'à', 'ä', 'â', 'ª', 'Á', 'À', 'Â', 'Ä'),
            array('a', 'a', 'a', 'a', 'a', 'A', 'A', 'A', 'A'),
            $string
        );
        $string = str_replace(
            array('é', 'è', 'ë', 'ê', 'É', 'È', 'Ê', 'Ë'),
            array('e', 'e', 'e', 'e', 'E', 'E', 'E', 'E'),
            $string
        );
        $string = str_replace(
            array('í', 'ì', 'ï', 'î', 'Í', 'Ì', 'Ï', 'Î'),
            array('i', 'i', 'i', 'i', 'I', 'I', 'I', 'I'),
            $string
        );
        $string = str_replace(
            array('ó', 'ò', 'ö', 'ô', 'Ó', 'Ò', 'Ö', 'Ô'),
            array('o', 'o', 'o', 'o', 'O', 'O', 'O', 'O'),
            $string
        );
        $string = str_replace(
            array('ú', 'ù', 'ü', 'û', 'Ú', 'Ù', 'Û', 'Ü'),
            array('u', 'u', 'u', 'u', 'U', 'U', 'U', 'U'),
            $string
        );
        $string = str_replace(
            array('ñ', 'Ñ', 'ç', 'Ç'),
            array('n', 'N', 'c', 'C',),
            $string
        );
        //Esta parte se encarga de eliminar cualquier caracter extraño
        if($esTag == 1)
            $reemplazo = '-';
        else
            $reemplazo = ' ';

        $string = str_replace(
            array("\\", "¨", "º", "-", "~",
                 "#", "@", "|", "!", "\"",
                 "·", "$", "%", "&", "/",
                 "(", ")", "?", "'", "¡",
                 "¿", "[", "^", "`", "]",
                 "+", "}", "{", "¨", "´",
                 ">", "< ", ";", ",", ":",
                 ".", " "),
            $reemplazo,
            $string
        );
        if($sbstr > 0)
        $string = substr($string,0,$sbstr);

        if($capital)
        return ucwords(strtolower(utf8_encode($string)) );
            else
        return utf8_encode($string);
    }

    public static function getIVAGeneral(){
        return 21; // NOTA!: Esto se debe tomar de un parámetro de configuración general
    }

    public static function getAlicuotaIva($coef)
    {
        return $coef * flxfn::getIVAGeneral(); 
    }
    /*
    * Funcion para asignar impuesto al producto
    *
    * @param int coef
    * @return int $coef id del impuesto para asignar al producto
    */
    public static function impuestoIva($coef)
    {
        if((int)Parametros::get('MS_USARIVA'))
        {
            // ID de la regla en prestashop
            $tax_rule = ($coef == 0.5 ? '10,5': '21');
            $return = (int)Db::getInstance()->getvalue("SELECT trg.id_tax_rules_group 
                                                      FROM "._DB_PREFIX_."tax_rules_group trg
                                                   WHERE trg.name LIKE '%".$tax_rule."%' AND trg.deleted = 0");
        }else{
            $return = (int)Db::getInstance()->getvalue("SELECT trg.id_tax_rules_group 
                                                            FROM "._DB_PREFIX_."tax_rules_group trg
                                                        WHERE trg.name LIKE '%21%' AND trg.deleted = 0");
        }//end if
        return $return;
    }

    /*
    * Funcion que desactiva categorias hijos en escala
    *
    * @param int $id_category id de la categoria padre
    * @param int status para activar o desactivar
    * @return true;
    */
    public static function updateChildrenStatus($id_category,$status)
    {
        /* Gets all children */
        $categories = Db::getInstance()->executeS('
            SELECT id_category, id_parent, level_depth
            FROM '._DB_PREFIX_.'category
            WHERE id_parent = '.(int)$id_category);

        /* Updates level_depth for all children */
        foreach ($categories as $sub_category)
        {
            Db::getInstance()->execute('
                UPDATE '._DB_PREFIX_.'category c
                 INNER JOIN '._DB_PREFIX_.'flx_c2c c2c ON c2c.id_prestashop = c.id_category
                   SET c.active = '.$status.'
                WHERE c.id_category = '.(int)$sub_category['id_category'].''.($status == 1 ?  ' AND c2c.muestraweb = "1"': ''));

            self::updateChildrenStatus($sub_category['id_category'],$status);
        }
        //return $categories;
    }

    
    /*
    * Agrega o actualiza las diferentes monedas del erp
    * @param int $id_currency  Codigo de Moneda
    * @param string $name Nombre de la moneda
    * @param float $conversion_rate Tasacion de la moneda
    *
    * @return true and false
    */
    public static function updateInsert($id_currency,$name,$conversion_rate,$tabla)
    {
       $id_equiv = (int)flxfn::equivalenciaID($id_currency,$tabla);
       $sql = 'SELECT id_prestashop FROM '._DB_PREFIX_.$tabla.' WHERE ID_ERP = "'.$id_equiv.'"';
       $result = (int)Db::getInstance()->getValue($sql);

       if($tabla == 'flx_monedas')
       {
           if($result == 0 and $id_equiv == 0)
             $result = Db::getInstance()->insert('flx_monedas',array(
                                   'ID_ERP' => $id_currency,
                                   'id_prestashop' => 0,
                                   'DESCRIPCION' => $name,
                                   'CAMBIO' => $conversion_rate,
                           ));
            elseif($id_equiv > 0){
              $Moneda = new Currency($id_equiv);
              $Moneda->iso_code = ($id_equiv == 1 ? 'ARS' : 'USD');
              $Moneda->sign = ($id_equiv == 1 || $id_equiv == 3 ? '$' : 'U$D');
              //$Moneda->format = ($id_equiv == 1 || $id_equiv == 3 ? 1 : 4);
              $Moneda->decimals = true;
              $Moneda->conversion_rate = (float)round($conversion_rate,2);
              //$Moneda->conversion_rate = (float)15.00;
              //$Moneda->update();
              $result = Db::getInstance()->update('flx_monedas',array(
                                'ID_ERP' => $id_currency,
                                'DESCRIPCION' => $name,
                                'CAMBIO' => $conversion_rate,
                        ),'ID_ERP="'.$id_currency.'"');
            }
       }elseif($tabla == 'flx_tiposiva'){
          if($result == 0 and $id_equiv == 0)
          Db::getInstance()->insert('flx_tiposiva', array(
              'ID_ERP' => $id_currency,
              'id_prestashop' => 0,
              'name' => $name,
              'tax' => $conversion_rate,
          ));
          elseif($id_equiv > 0)
          Db::getInstance()->update('flx_tiposiva', array(
              'ID_ERP' => $id_currency,
              'id_prestashop' => 0,
              'name' => $name,
              'tax' => $conversion_rate,
          ),'ID_ERP="'.$id_currency.'"');
       }

      return $result;
    }

    public static function getMonedas($id_erp = '',$erp = null)
    {

        if($erp != null)
        {
            $sql='SELECT m.ID_ERP,m.DESCRIPCION FROM '._DB_PREFIX_.'flx_monedas m ';
            $result = Db::getInstance()->executeS($sql);
            $i=0;
                foreach($result as $value):
                    $data[$i]['Codigo'] = $value['ID_ERP'];
                    $data[$i]['Nombre'] = $value['DESCRIPCION'];
                    $i++;
                endforeach;
            return $data;
        }
        
        if(empty($id_erp)){
          $sql = 'SELECT cs.id_currency,cs.name FROM '._DB_PREFIX_.'currency cs WHERE cs.active = 1 ';
          
          $result = Db::getInstance()->executeS($sql);
          return $result;
        }else{
          $sql='SELECT cs.id_currency FROM '._DB_PREFIX_.'currency cs
                  INNER JOIN '._DB_PREFIX_.'flx_monedas m ON (m.id_prestashop = cs.id_currency)
                WHERE cs.active = 1 and m.ID_ERP = "'.$id_erp.'"';

          $result = Db::getInstance()->getValue($sql);

          return $result;
        }

    }

    public function getEstados($id_erp = '',$erp = null)
    {

        if($erp != null)
        {
            $estados = array( 1 => 'PREPARADO', 2 => 'FACTURADO', 3 => 'REMITIDO', 4 => 'DESPACHADO', 5 => 'ANULADA');
            foreach ($estados as $key => $value):
              $data[$key]['Codigo'] = $value;
              $data[$key]['Nombre'] = $value;
            endforeach;
            return $data;
        }

        if(empty($id_erp)){
          $sql='SELECT slg.id_order_state,slg.name FROM '._DB_PREFIX_.'order_state_lang slg
                WHERE slg.id_lang = '.(int)Configuration::get('PS_LANG_DEFAULT');

          $result = Db::getInstance()->executeS($sql);

          return $result;
        }else{
          $sql='SELECT slg.id_order_state FROM '._DB_PREFIX_.'order_state_lang slg
                  INNER JOIN '._DB_PREFIX_.'flx_estados_np enp ON (slg.id_order_state = enp.id_prestashop)
                WHERE slg.id_lang = '.(int)Configuration::get('PS_LANG_DEFAULT').' and enp.ID_ERP ='.$id_erp;

          $result = Db::getInstance()->getRow($sql);

          return $result;
        }

    }

    public static function getEstadosCombo()
    {
        $orderStates = Db::getInstance()->ExecuteS("SELECT * FROM "._DB_PREFIX_."order_state_lang WHERE id_lang = ".(int)Configuration::get('PS_LANG_DEFAULT'));
        $estado = explode(",",Parametros::get('MS_ESTADOSPEDIDOS_'.Context::getContext()->shop->id));
        $html = '';
        
        foreach ($orderStates as $order)
        {
            $html .= '<option value="'.$order['id_order_state'].'"';
            
              foreach($estado as $val)
              {
                $html .= ($val == $order['id_order_state'] ? 'selected="selected"' : '');
              }
            
            $html .= '>'.$order['name'].'</option>';
        }

        return $html;
    }

    public static function getListaPrecios($html='')
    {
        $listaPrecios = array('1','2','3','4','5');
         foreach ($listaPrecios as $precio)
        $html .= '<option value="'.$precio.'"'.(Parametros::get('MS_LISTAPRECIO') == $precio ? ' selected="selected"' : '').'>'.
                $precio.'</option>';
        return $html;
    }

    /*
    * Agregar Direccion de los distintos clientes
    * @param array $direccion 
    * @return true en el caso de finalizar
    */
    public static function addAddress($direccion)
  	{
  		  $search_direccion = substr(self::getValidestrName($direccion['direccion'], 128,true), 0, 25);

        // Busco si existe la dirección enviada. De existir actualiza sino crea.
        $id_address = (int)Db::getInstance()->getValue("SELECT ad.id_address
                                                        FROM "._DB_PREFIX_."address ad
                                                    WHERE ad.id_customer=".$direccion['id_cliente']." and address1 like '".$search_direccion."%' ");

        $postcode = self::letraCapital(trim($direccion['cp']));
        $postcode = (empty($postcode) || $postcode == '-' || strlen($postcode) <= 3 ? '0000' : $postcode );
        $telefono = self::phoneFormat($direccion['telefono'],'TEL');
  		$celular = self::phoneFormat($direccion['telefono'],'CEL');
        $addres = new Address($id_address);
        $addres->id_customer = (int)$direccion['id_cliente'];
        $addres->id_country = (int)44;
        $addres->id_state = (int)self::getIdByName($direccion['codprovincia']);
        $addres->alias = 'Mi Casa';
        $addres->lastname = self::letraCapital($direccion['lastname']);
        $addres->firstname = self::letraCapital($direccion['firstname']);
        $addres->postcode = $postcode;
        $addres->address1 = self::getValidestrName($direccion['direccion'], 128,true);
        $addres->city = self::getValidestrName($direccion['localidad'], 64, true);
        $addres->phone = self::getValidestrName($telefono, 64, true);
        //$addres->phone_mobile = self::getValidestrName($celular, 64, true);
        $addres->dni = $direccion['dni'];
        try{
            $addres->save();
        }catch(Exception $e){
            Db::getInstance()->execute('INSERT INTO '._DB_PREFIX_.'flx_log_error
                                          VALUES("","Error Address","ID Address:'.$addres->id.' | '.$e->getMessage().' Linea error ('.$e->getLine().')'.'",
                                          "Pendiente","'.$_SERVER['SERVER_ADDR'].'","'.date('Y-m-d H:m:s').'");');
        }
  		return true;
  	}

    /*** Envio de mail de log ***/
    public static function sendMail($subject, $content, $modbug = false)
    {
        
        $FechaSincro = new DateTime(self::getFechaUltimaSincro('MTO_ALERTASINCRO',0));
        $fechaInicioSincro = new DateTime("now");

        $interval = $FechaSincro->diff($fechaInicioSincro);
        //esto envia mail si paso 1 dia desde el ultimo mail enviado
        
        if( $interval->format('%R%a') >= 1){

          $smtpChecked = 'smtp';
          $smtpServer = Configuration::get('PS_MAIL_SERVER');
          $content = urldecode($content);
          $content = html_entity_decode($content);
          $subject = urldecode($subject);
          $type = 'text/html';
          //$to = 'ecommerce@flexxus.com.ar';

          // $to = (!$modbug ? 'alertamto@flexxus.com' : Configuration::get('PS_SHOP_EMAIL'));
          // $from = (!$modbug ? 'alertamto@flexxus.com' : Configuration::get('PS_MAIL_USER'));
          $to = 'alertamto@flexxus.com';
          $from = 'alertamto@flexxus.com';
          $smtpLogin = Configuration::get('PS_MAIL_USER');
          $smtpPassword = Configuration::get('PS_MAIL_PASSWD');

          $smtpPort = Configuration::get('PS_MAIL_SMTP_PORT');
          $smtpEncryption = 'ssl';
          Mail::sendMailTest(Tools::htmlentitiesUTF8($smtpChecked), Tools::htmlentitiesUTF8($smtpServer), Tools::htmlentitiesUTF8($content), Tools::htmlentitiesUTF8($subject), Tools::htmlentitiesUTF8($type), Tools::htmlentitiesUTF8($to), Tools::htmlentitiesUTF8($from), Tools::htmlentitiesUTF8($smtpLogin), $smtpPassword, Tools::htmlentitiesUTF8($smtpPort), Tools::htmlentitiesUTF8($smtpEncryption));
                    
          self::updateFechaUltimaSincro('MTO_ALERTASINCRO', date_format($fechaInicioSincro,'m/d/Y') ,0);

        

          // Enviar mail metodo basico php

          $content .= ' '.Configuration::get('PS_SHOP_EMAIL');
          $subject .= ' '.Configuration::get('PS_SHOP_EMAIL');

          mail('alertamto@flexxus.com', $subject, $content, 'From: alertamto@flexxus.com');


        }


        


    }

    /*** Agrego error en la tabla log ***/
    public static function getLog()
    {
        $return =  Db::getInstance()->executeS("SELECT * FROM "._DB_PREFIX_."flx_log_error WHERE evento <> 'Conexion'");

        return $return;
    }

    /*** Agrego error en la tabla log ***/
    public static function addLog($error, $message)
    {
        $message = self::formatMessage($message);
        $return = true;
        $return &=  Db::getInstance()->insert('flx_log_error', array(
                      'evento' => $error,
                      'error' => $message,
                      'estado' => 'Pendiente',
                      'ipconeccion' => $_SERVER['SERVER_ADDR'],
                      'fechahora' => date('Y-m-d H:m:s')
                    ));

        /*$content = '<h2> Datos de Cliente</h2><br/>';
        $content.= 'Servidor: '._DB_SERVER_.'<br/>';
        $content.= 'Servidor: '._DB_NAME_.'<br/>';
        $content.= 'Error'.$e->getMessage().' Linea error ('.$e->getLine().')';

        self::sendMail('Error Sincronizacion: Productos',$content);*/

        return $return;
    }

    /*** Agrego error en la tabla log ***/
    public static function formatMessage($message)
    {
        if (strpos($message, 'Manufacturer->name') || strpos($message, 'Category->name') || strpos($message, 'Product->name'))
            $message = 'El Nombre esta vacio.';

        if (strpos($message, 'MTO_ERROR_GENERICO'))
            $message = strstr($message, 'MTO_ERROR_GENERICO');
        if (strpos($message, 'Parent category does not exist'))
            $message = 'La categoria padre no existe.';
        if (strpos($message, 'conversion error from string'))
            $message = 'Tiene campos en su pedido que no pueden estar vacio.';
        if (strpos($message, 'Address->phone'))
            $message = 'El campo telefono no puede quedar vacio.';
        if (strpos($message, 'SpecificPrice->reduction'))
            $message = 'El Precio de Descuento no es valido.';
        if (strpos($message, 'Duplicate entry'))
            $message = 'Error de duplicados.';
            

        return $message;
    }

    public static function printError($error, $type = 'vardump')
    {
      $html = '<pre>';
        $html .= ($type == 'vardump'? var_dump($error) : print_r($error));
      $html .= '</pre>';
      return $html;
    }

    public static function initProgressBar($name_objSincro, $sql_objsincro, &$position, $mododebug, $ibase, $TOTALINIT = 0)
    {
      $position = -1;
      $TOTALREG = $TOTALINIT;

      if((int)$mododebug)
      {
        echo '<script language="javascript">
        document.getElementById("obj_sincro").innerHTML="Obteniendo '.$name_objSincro.'...";
        </script>';
        if($sql_objsincro != '')
        {
            if($ibase->engine == 2){
              $result_sql = $ibase->query($sql_objsincro);
              $sql_Count = "SELECT @@Rowcount AS TOTALREG ";
              $ibase->free_result($result_sql);
            }
            else {
              $sql_Count = "Select distinct Count(*) AS TOTALREG FROM (".$sql_objsincro.") S ";
            }
            //echo $sql_Count;
            $result_Count = $ibase->query($sql_Count);
            $row = $ibase->fetch_object($result_Count);
            $TOTALREG = $row->TOTALREG;
            $ibase->free_result($result_Count);
        }

        echo '<script language="javascript">
        document.getElementById("obj_sincro").innerHTML="Sincronizando '.$name_objSincro.'...";
        </script>';

        self::updateProgressBar($position, $TOTALREG);

      }
      return $TOTALREG;
    }

    public static function updateProgressBar(&$position, $TOTALREG)
    {
      if($TOTALREG > 0)
      {
        $position++;
        $percent = intval($position/$TOTALREG * 100)."%";

        // Javascript for updating the progress bar and information
        echo '<script language="javascript">
        document.getElementById("progress").innerHTML="<div style=\"width:'.$percent.';background-color:#ddd;\">&nbsp;</div>";
        document.getElementById("information").innerHTML="'.$position.' de '.$TOTALREG.' registros";
        </script>';
      }
    }

    public static function endProgressBar($name_objSincro, $TOTALREG, $TOTAL_Errores)
    {
      if($TOTALREG > 0)
      {
        echo '<script language="javascript">
          document.getElementById("textresult").value += "'.$TOTALREG.' '.$name_objSincro.' sincronizados - '.$TOTAL_Errores.' errores \n"
        </script>';
      }
    }

    /*
    * Traemos todas las combinaciones del producto
    * @param $id_product integer
    **/
    public static function getAllCombination($id_product)
    {
        $combination = [];
        $result = Db::getInstance()->executeS("SELECT * FROM "._DB_PREFIX_."flx_pa2pa WHERE ID_ERP LIKE '".$id_product."%'");
        foreach($result as $r => $k)
        {
            $combination[$k['id_prestashop']] = false;
        }//end foreach

        return $combination;
    }

    /*
    * Note: Trae la version del modulo en la base de datos.
    */
    public static function getModuleVersion()
    {
        return Db::getInstance()->getValue('SELECT version
                                                    FROM `'._DB_PREFIX_.'module`
                                                WHERE `name` = "flxsincro"');
    } 
    
    public static function cleanCache()
    {
        Tools::enableCache();
        Tools::clearCache(Context::getContext()->smarty);
        Tools::restoreCacheSettings();
    }

    public static function truncateNumberDecimals($number, $decimals)
    {
        ini_set('precision', 25);
        $point_index = strrpos($number, '.');
        $NumeroFormateado=substr($number, 0, $point_index + $decimals+ 1);
        ini_set('precision', 14);
        return $NumeroFormateado;
    }

    public static function esPack($id_product)
    {

      $resultado = DB::getInstance()->getValue('SELECT pack_stock_type FROM '._DB_PREFIX_.'product WHERE id_product = '.$id_product);
      
      return $resultado;

    }

    public static function articuloStockNegativo($id_product)
    {

      $resultado = DB::getInstance()->getValue('SELECT out_of_stock FROM '._DB_PREFIX_.'stock_available WHERE id_product = '.$id_product);
      
      return $resultado;

    }

    // Envia mail de bienvenida para establecer contraseña de un mayorista
    public static function sendMailWelcome($customer)
    {
      if (!$customer->hasRecentResetPasswordToken()) {
          $customer->stampResetPasswordToken();
          $customer->update();
      }

      $mailParams = array(
        '{email}' => $customer->email,
        '{lastname}' => $customer->lastname,
        '{firstname}' => $customer->firstname,
        '{url}' => Context::getContext()->link->getPageLink('password', true, null, 'token='.$customer->secure_key.'&id_customer='.(int) $customer->id.'&reset_token='.$customer->reset_password_token),
      );

     $result = Mail::Send(
        Context::getContext()->language->id,
        'new_password_customer',
        "Bienvenido",
        $mailParams,
        $customer->email,
        $customer->firstname.' '.$customer->lastname
      );
    }

    // Devuelve la condición de IVA
    public static function getIva($id_customer)
    {
      $sql = 'SELECT other FROM '._DB_PREFIX_.'address
              WHERE id_customer = '.$id_customer;
      $result = Db::getInstance()->executes($sql)[0]["other"];
      
      if (empty($result)){
        return false;
      }
      else{
        return $result;
      }
    }

    /* Ejecuta un update con los cammpos parametrizados
      $table: Tabla donde se quiere realizar el update
      $id: PK de la tabla
      $id_value: valor de PK donde se va a hacer el update
      $fields: Arreglo asociativo que contiene el nombre del campo a modificar y el valor
    */
    public static function customUpdate($table,$id,$id_value,$fields)
    {
      $keys   = array_keys($fields);
      $update = "UPDATE "._DB_PREFIX_.$table;
      $set    = "SET ";
      $where  = " WHERE ".$id." = ".$id_value;

      foreach ($fields as $key => $field) {
        if ($key === end($keys)) {
          $set .= $field['field']." = '".$field['value']."'";
        }
        else{
          $set .= $field['field']." = '".$field['value']."', ";
        }       
      }

      $query = $update." ".$set." ".$where;      
      return Db::getInstance()->execute($query);

    }

    // Realiza el update de precio de un producto determinado
    public static function updateProductPrice($id_product, $price)
    {
      $fields = array(['field' => 'price',           'value' =>  $price],
                      ['field' => 'wholesale_price', 'value' =>  $price],
                      ['field' => 'date_upd',        'value' =>  date("Y-m-d H:i:s")]);      
      
      if (!flxfn::customUpdate("product","id_product",$id_product,$fields) || !flxfn::customUpdate("product_shop","id_product",$id_product,$fields)){
        return 0;                                 
      }

      return 1;
    }

    // Limpia los atributos y stock sobrantes
    public static function cleanAttributesAndStock(){
        $resultAttributeShop = Db::getInstance()->execute("DELETE
                                                          FROM mto_product_attribute_shop
                                                          WHERE id_product_attribute NOT IN (SELECT id_prestashop 
                                                                                             FROM mto_flx_pa2pa);");
        $resultAttributeFlx  = Db::getInstance()->execute("DELETE
                                                          FROM mto_product_attribute
                                                          WHERE id_product_attribute NOT IN (SELECT id_prestashop 
                                                                                            FROM mto_flx_pa2pa);");
        $resultAttribute     = Db::getInstance()->execute("DELETE 
                                                           FROM mto_product_attribute
                                                           WHERE  id_product_attribute NOT IN (SELECT id_product_attribute
                                                                                          FROM mto_product_attribute_shop);");
        $resultStock         = Db::getInstance()->execute("DELETE 
                                                          FROM mto_stock_available
                                                          WHERE id_product_attribute NOT IN (SELECT id_product_attribute
                                                                                            FROM mto_product_attribute)
                                                                                            AND id_product_attribute != 0 ;");
  
        if($resultAttribute != 1 || $resultStock != 1 || $resultAttributeShop != 1 || $resultAttributeFlx != 1) {
          echo "<br> Hubo un error al borrar atributos y stocks de más <br>";  
        }
        else {
          echo "<br> Se borraron correctamente los atributos y stocks de más <br>";  
        }
        
    }
    public static function deleteFromFlxClient($idClientPresta, $idClientErp){
        
        echo '<br> <b>Cliente con error</b> <br>';
        echo '>> id_presta: ' . $idClientPresta . ' -> id_erp: ' . $idClientErp . '<br>';
        
        echo '--------------------------------------------------------------------------- <br>';
        echo ' Se eliminará la relación <br>';
        echo ' El cliente ya no posee el mismo ID_ERP. Es necesario eliminar la relación.  <br>';
        echo '--------------------------------------------------------------------------- <br>';
        
        flxfn::startTransaction();

        $sql = 'DELETE FROM '._DB_PREFIX_.'flx_cliente
        WHERE id_prestashop = ' . $idClientPresta;
        
        $result = Db::getInstance()->execute($sql);

        if ($result) {
            echo '...<br>';
            echo '...Cliente eliminado <br>';
        } else {
            echo '...<br>';
            echo '...Ocurrio un error<br>';
        }

        flxfn::commitTransaction(0);

        return $result;
    }
}
?>
