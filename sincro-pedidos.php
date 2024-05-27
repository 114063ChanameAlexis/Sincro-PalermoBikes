<?php
ini_set("memory_limit", "-1");
/* Debug only */
if (!defined('_PS_MODE_DEV_') && isset($_GET['mododebug']) and $_GET['mododebug'] == 1){
  define('_PS_MODE_DEV_', true);
}
require ('../../config/config.inc.php');
include (dirname(__FILE__) . '/flxsincro.php');
 
$sincro = new Sincro();

if(_PS_MODE_DEV_):
// Turn off output buffering
ini_set('output_buffering', 'off');
// Turn off PHP output compression
ini_set('zlib.output_compression', false);

//Flush (send) the output buffer and turn off output buffering
while(@ob_end_flush());

// Implicitly flush the buffer(s)
ini_set('implicit_flush', true);
ob_implicit_flush(true);
set_time_limit(3000);
endif;

$ID_SHOP = (Context::getContext()->shop->id  == 0 ? (int)Configuration::get('PS_SHOP_DEFAULT') : Context::getContext()->shop->id );

//activar modo debug
$mododebug = (isset($_GET['mododebug']) and $_GET['mododebug'] == 1 ? 1 : 0);
$descount = (isset($_GET['descount']) ? false : true);
$TIPOFORM = Parametros::get('MS_TIPOFORM');

/* ----> INICIO - CONEXION MOTOR ERP */
    $engine = Parametros::get('MS_ENGINE');
    // En el parámetro MS_SERVIDOR actualmente se está almacenando la lista de Host separados por ;
    $host = explode(';',Parametros::get('MS_SERVIDOR_'.$ID_SHOP));

    // En el parámetro MS_HOST actualmente se está almacenando la ruta de la BD (Firebird) o el nombre de la BD (SQL Server)
    $database = Parametros::get('MS_HOST_'.$ID_SHOP);

    $usuario = Parametros::get('MS_USUARIO_'.$ID_SHOP);
    $password = Parametros::get('MS_PASSWORD_'.$ID_SHOP);

    try{
        $ibase = new flxibase($engine,$host[0],$database,$usuario,$password);
        if($ibase->connect() == false)
            throw new Exception('reintentar');

        if($mododebug == 1)
          echo 'Conectado a '.$host[0].'<br/><br/>';

    }catch(Exception $e){
        if($e->getMessage() === 'reintentar'){
            $ibase = new flxibase($engine,$host[1],$database,$usuario,$password);
            if($ibase->connect() == false){
                Db::getInstance()->execute('INSERT INTO '._DB_PREFIX_.'flx_log_error VALUES("","Conexion","'.$e->getMessage().' Linea error ('.$e->getLine().')'.'","Pendiente","'.$_SERVER['SERVER_ADDR'].'","'.date('Y-m-d H:m:s').'");');
                $data['mensaje']['Error'] = 'No se ha podido conectar con la base de datos del ERP. Vuelva a intentarlo mas tarde';
                //echo 'Error al Sincronziar. Intentelo mas tarde';
                    $content = '<h2> Datos de Cliente</h2><br/>';
                    $content.= 'Servidor: '._DB_SERVER_.'<br/>';
                    $content.= 'Servidor: '._DB_NAME_.'<br/>';
                    $content.= 'Error al conectarse al ERP';
                    flxfn::sendMail('Error Sincronizacion: '._DB_NAME_, $content , true);
                 $ibase->close();
                    echo Tools::jsonEncode($data);
                exit;
            }

            if($mododebug == 1)
              echo 'Conectado a '.$host[1].'<br/><br/>';
        }//end $e->getMessage()

    }
/* ----> FIN - CONEXION DBMS*/

/* ----> INICIO - Integración de PARAMETROS  */
 if($TIPOFORM == 1)
 {
   //Parametro para Usar Stock de Factura sin remitir
   $sql= "SELECT VALOR FROM PARAMETROSGENERALES WHERE CODIGOPARAMETRO = 82";
   $result_parametro = $ibase->query($sql);
   $row = $ibase->fetch_object($result_parametro);
   $FACTURASINREMITIR = ((int)$row->VALOR == 1);
   $ibase->free_result($result_parametro);

   //Parametro para usar Talle
   $sql= "SELECT VALOR FROM PARAMETROSGENERALES WHERE CODIGOPARAMETRO = 83";
   $result_parametro = $ibase->query($sql);
   $row = $ibase->fetch_object($result_parametro);
   $USATALLE = (int)$row->VALOR;
   $ibase->free_result($result_parametro);

   if($USATALLE == 1)
    $USALOTE = 0;
   else{
     //Parametro para usar Lote
     $sql= "SELECT VALOR FROM PARAMETROSGENERALES WHERE CODIGOPARAMETRO = 20";
     $result_parametro = $ibase->query($sql);
     $row = $ibase->fetch_object($result_parametro);
     $USALOTE = (int)$row->VALOR;
     $ibase->free_result($result_parametro);
   }
 }
 else {
   $USATALLE = 0;
   $USALOTE = 0;
   $FACTURASINREMITIR = false;
 }

$ID_LANG = (int)Configuration::get('PS_LANG_DEFAULT');

$MS_MAYORISTA = ((int)Parametros::get('MS_MAYORISTA_'.Context::getContext()->shop->id));
$MS_DEPOSITOS = (Parametros::get('MS_DEPOSITOS_'.Context::getContext()->shop->id));
$MS_CATEGORIASASOC = (Parametros::get('MS_CATEGORIASASOC_'.Context::getContext()->shop->id));
$listaGSR = (!empty($MS_CATEGORIASASOC) ? "'".$MS_CATEGORIASASOC."'" : 'null');
$MS_TALLES = (int)Parametros::get('MS_TALLES');
$MS_LISTAPRECIO = Parametros::get('MS_LISTAPRECIO');
$MS_STOCKREAL = (int)Parametros::get('MS_STOCKREAL');
$MS_CONDICIONIVA = Parametros::get('MS_CONDICIONIVA');
$MS_BARRIO = Parametros::get('MS_BARRIO');
$MS_CODZONA = Parametros::get('MS_CODZONA');
$MS_USERERP = Parametros::get('MS_USERERP_'.Context::getContext()->shop->id);
$MS_USERCOBRADOR = Parametros::get('MS_USERCOBRADOR_'.Context::getContext()->shop->id);
$MS_CODACTIVIDAD = Parametros::get('MS_CODACTIVIDAD_'.Context::getContext()->shop->id);
$MS_CODFORMADEPAGO = Parametros::get('MS_CODFORMADEPAGO_'.$ID_SHOP);
$MS_CODOPERACION = Parametros::get('MS_CODOPERACION_'.Context::getContext()->shop->id);
$MS_ESTADOSPEDIDOS = Parametros::get('MS_ESTADOSPEDIDOS_'.Context::getContext()->shop->id);
$MS_CODTRANSPORTISTA = Parametros::get('MS_CODTRANSPORTISTA');
$MS_CODMONEDA = Parametros::get('MS_CODMONEDA_'.$ID_SHOP);
$MS_LOCSNA = Parametros::get('MS_LOCSNA');
$MS_NOMBREARTICULO = Parametros::get('MS_NOMBREARTICULO');
$MS_DEPXCLI = Parametros::get('MS_DEPXCLI_'.Context::getContext()->shop->id);

$TaxList = flxfn::getTaxList();
$idFlete = flxfn::getDateErp('getFlete',$MS_CODTRANSPORTISTA,false);
$MS_DEPOSITOSSTOCK = Parametros::get('MS_DEPOSITOSSTOCK');
//Traemos todos los grupos de los clientes.
$groups = flxfn::getGroups($ID_LANG);

/* ----> FIN - Integracion de PARAMETROS */

/* ----> INICIO - PARAMETRO FECHA SINCRO */
$date = new DateTime($ibase->getDBMS_DateTime());
$fechaInicioSincro = $date->format('Y-m-d H:i');

  echo 'Inicio Sincro: '.$fechaInicioSincro.'<br/>';
  echo ('</br>'.
        '<div id="obj_sincro" style="width"></div>'.
        '<div id="progress" style="width:500px;border:1px solid #ccc;display:none"></div>'.
        '<div id="information" style="width"></div></br>'.
        '<textarea style="width: 500px; height: 150px;" rows="10" id="textresult"></textarea>');

/* ----> FIN - PARAMETRO FECHA SINCRO */

/* ----> INICIO - INTEGRACION DE CLIENTES */
$RegistrosSincronizados = 0;$errMensaje = 0;
flxfn::startTransaction();
//inicio para tienda mayorista
if($MS_MAYORISTA)
{

    $fechaSincro = flxfn::ultimaFechaSincro('MTO_CLIENTES',$ID_SHOP);
    $sql_clientes ="SELECT * FROM MTO_CLIENTES('".$fechaSincro."') WHERE EMAIL <> '' or EMAIL <> '-'";

    $TOTALREG = flxfn::initProgressBar('Clientes -> MTO', $sql_clientes, $position, (int)$mododebug, $ibase);
    $registrosSalteados = 0;
    $result_clientes = $ibase->query($sql_clientes);
    try{
        if($result_clientes === false)
          throw new Exception('MTO_CLIENTES');

    while($row = $ibase->fetch_object($result_clientes))
    {
        flxfn::updateProgressBar($position, $TOTALREG);
        $email = explode(",",$row->EMAIL);

        $clienteMayorista = (int)$row->PEDIDOSWEB;

                if(Validate::isEmail($email[0]))
                {
                    $id_cliente = flxfn::equivalenciaID($row->CODIGOPARTICULAR,'flx_cliente');
                    if($id_cliente == 'NULL' && (int)$row->ACTIVO == 0) {
                        $registrosSalteados = $registrosSalteados + 1;
                        continue;
                    }

                    if ($fechaSincro != '1900-01-01')
                        $clientes_Modify[] = $row->CODIGOPARTICULAR;

                    $id_clienteERP = $row->CODIGOPARTICULAR;



                    $NombreCompleto = str_word_count(trim($row->RAZONSOCIAL),1);
                
                    $cliente = new Customer($id_cliente);
                    $cliente->id_gender = 1;

                    $cliente->firstname = PSQL(flxfn::getValidestrName($NombreCompleto[0]));
                    $lastname = (count($NombreCompleto) > 1 ? $NombreCompleto[1] : '-');
                    $cliente->lastname = pSQL(flxfn::getValidestrName($lastname));
                    $cliente->id_shop = (int)$ID_SHOP;
                    $cliente->email = pSQL(flxfn::letraCapital($email[0]));
                    /*
                    * Cuando la condicion es mayorista el cliete usa con clave
                    * de logueo su cuit dado de alta en flexxus, de lo contrario
                    * recibe una clave generica
                    **/

                    
                    if ($id_cliente == 'NULL') {
                        $cuit = trim(str_replace('-','',$row->CUIT));
                        if($cuit == '' )
                            $cliente->passwd = Tools::encrypt('12345678');
                        else
                            $cliente->passwd = Tools::encrypt($cuit);
                    }

                    $cliente->website = pSQL('');

                    //if($cliente->id_shop != 1)
                    $cliente->id_default_group = flxfn::listaDefault($row->LISTA);
                    $cliente->date_upd = date('Y-m-d');
                    
                    //original: $list = array('1', '2', '3', flxfn::listaDefault($row->LISTA));
                    
                    $listMay = array(flxfn::listaDefault($row->LISTA));
                    //$list = array('1', '2', '3', flxfn::listaDefault($row->LISTA));
                    $list = array('1', '2', '3');

                if($clienteMayorista == 1){

                    if($id_cliente == 'NULL'){
                        $cliente->active = 1;
                        try{
                            $cliente->add();
                        }catch(Exception $e){
                            $message = $e->getMessage().' Linea error ('.$e->getLine().')';
                            flxfn::addLog('Agregar Cliente', $message);
                            $errMensaje = $errMensaje + 1;
                            continue;
                        }
                    
                        //original: $cliente->addGroups(array('1','2','3'));
                        $cliente->addGroups(flxfn::listaDefault($row->LISTA));
                        
                        //$cliente->cleanGroups();
                        //$cliente->addGroups(array(flxfn::listaDefault($row->LISTA)));
                    } elseif($id_cliente != 'NULL') {
                        //original: quitar prox linea
                        //$cliente->cleanGroups();
                        //original: quitar linea anterior

                        $cliente->active = (int)$row->ACTIVO;
                        
                        /*if ($cliente->getGroups()==flxfn::listaDefault($row->LISTA))
                        {   
                            $list = array($cliente->getGroups(), flxfn::listaDefault($row->LISTA));
                            
                        }????*/
                        
                        $cliente->updateGroup($listMay);            
                        
                        try{
                            $cliente->update();
                        }catch(Exception $e){
                            $message = $e->getMessage().' Linea error ('.$e->getLine().')';
                            flxfn::addLog('Actualizar Cliente', $message);
                            $errMensaje = $errMensaje + 1;
                            continue;
                        }
                    }
                }else{

                    $cliente->updateGroup($list);
                    try{
                            $cliente->update();
                        }catch(Exception $e){
                            $message = $e->getMessage().' Linea error ('.$e->getLine().') Error al actualizar Grupos del Cliente minorista. ID del cliente '.$id_cliente;
                            flxfn::addLog('Actualizar Cliente', $message);
                            $errMensaje = $errMensaje + 1;
                            continue;
                        }

                }

                /* Agrega direcion de los clientes */
                if($cliente->id > 0) {
                    $direcciones = array('codprovincia' => $row->CODIGOPROVINCIA,
                        'id_cliente' => $cliente->id,
                        'firstname' => trim($NombreCompleto[0]),
                        'lastname' => trim($lastname),
                        'cp' => trim($row->CP),
                        'direccion' => trim($row->DIRECCION),
                        'localidad' => trim($row->LOCALIDAD),
                        'telefono' => trim($row->TELEFONO),
                        'dni' => 00000000,
                    );

                    flxfn::addAddress($direcciones);
                }

                //Insertamos la equivalencia.
                if($id_cliente == 'NULL'){
                    Db::getInstance()->insert('flx_cliente', array(
                          'ID_ERP' => $id_clienteERP,
                          'id_prestashop' => $cliente->id,
                          'CODIGOIVA' => $row->CONDICIONIVA,
                          'CODVENDEDOR' => utf8_encode($row->CODIGOVENDEDOR)
                      ));
                 }elseif($id_cliente != 'NULL' && $cliente->id > 0){
                     Db::getInstance()->update('flx_cliente', array(
                          'CODIGOIVA' => $row->CONDICIONIVA,
                          'CODVENDEDOR' => utf8_encode($row->CODIGOVENDEDOR)
                      ),'ID_ERP="'.$id_clienteERP.'"');
                }//INSERTAR EQUIVALENCIAS

                /* ----> INICIO - Integración de Bonificacion */
                    $params = array('id_customer' => $cliente->id, 'restriction' => false);
                    $id =  flxfn::getIdBonification($params);
                    $cartRule = new CartRule($id);
                    $cartRule->id_customer = $cliente->id;        
                    $cartRule->name = array($ID_LANG => 'Descuento General '.$row->BONIFICACION.'% - '.$row->RAZONSOCIAL);
                    $cartRule->description = 'Sincronizado el '.date('d-m-Y H:i:s');
                    $cartRule->quantity = 1000000000;
                    $cartRule->quantity_per_user = 1000000000;
                    $cartRule->date_from = date('Y-m-d');
                    $cartRule->date_to  = date('Y-m-d', strtotime('2020-01-01'));
                    $cartRule->reduction_percent = (float)$row->BONIFICACION;
                    if((float)$row->BONIFICACION > 0.00)
                    {
                        if($id == 0)
                        {
                            $cartRule->date_add = date('Y-m-d');
                            try{
                                $cartRule->add();
                            }catch(Exception $e){
                                $message = $e->getMessage().' Linea error ('.$e->getLine().')';
                                flxfn::addLog('Agregar Bonificacion', $message);
                                $errMensaje = $errMensaje + 1;
                                continue;
                            }
                        }else{
                            $cartRule->date_upd = date('Y-m-d');                   
                            try{
                                $cartRule->update();
                            }catch(Exception $e){
                                $message = $e->getMessage().' Linea error ('.$e->getLine().')';
                                flxfn::addLog('Actualizar Bonificacion', $message);
                                $errMensaje = $errMensaje + 1;
                                continue;
                            }
                        }

                    }elseif((int)$row->BONIFICACION == 0 && $id != 0){            
                        $cartRule->delete();           
                    }
                /* ----> FIN - Integración de Bonificacion */

                /* ----> INICIO - Integración de DESCUENTOS X ARTICULOS, MARCAS, CATEGORIAS */
                if((int)$descount) {
                    $errMensaje += $sincro->DescuentoCliente($id_clienteERP, $cliente->id);
                }
                /* ----> FIN - Integracion de DESCUENTOS */
                $RegistrosSincronizados = $RegistrosSincronizados + 1;
                
                }
              
                else{
                    $registrosSalteados = $registrosSalteados + 1;
                    }
        
    }//EndWhile

    $ibase->free_result($result_clientes);

    flxfn::updateFechaUltimaSincro('MTO_CLIENTES', $fechaInicioSincro,$ID_SHOP);

    }catch(Exception $e){
          Db::getInstance()->execute('INSERT INTO '._DB_PREFIX_.'flx_log_error VALUES("","Sincronizacion","'.$e->getMessage().' Linea error ('.$e->getLine().')'.'","Pendiente","'.$_SERVER['SERVER_ADDR'].'","'.date('Y-m-d H:m:s').'");');

            $content = '<h2> Datos de Cliente</h2><br/>';
            $content.= 'Servidor: '._DB_SERVER_.'<br/>';
            $content.= 'Servidor: '._DB_NAME_.'<br/>';
            $content.= 'Error'.$e->getMessage().' Linea error ('.$e->getLine().')';

            flxfn::sendMail('Error Sincronizacion: Clientes',$content);
    }
    flxfn::endProgressBar('clientes', $TOTALREG, $TOTALREG-$RegistrosSincronizados-$registrosSalteados);
}//END TIENDAMAYORISTA

  $clientes = flxfn::getCustomers(flxfn::ultimaFechaSincro('MTO_CUSTOMER',$ID_SHOP));
  //var_dump($clientes);
  $TOTALREG = flxfn::initProgressBar('Clientes -> ERP', '', $position, (int)$mododebug, $ibase, count($clientes));

  foreach($clientes as $cliente => $c)
  {
    flxfn::updateProgressBar($position, $TOTALREG);

            $id_provincia = flxfn::equivalenciaID($c['CODIGOPROVINCIA'],'flx_provincia',false);
            $condicionIva = (flxfn::equivalenciaCondicionIva($c['id_customer'])? flxfn::equivalenciaCondicionIva($c['id_customer']) : $MS_CONDICIONIVA);

            $razon_social = ($c['EMPRESA'] != '' && $c['CUIT'] != '' && $MS_MAYORISTA ? $c['EMPRESA'] : substr($c['razon_social'],0,50));
           
            $LOCSNA = ($MS_LOCSNA != '' ? explode(",",$MS_LOCSNA) : '');
            $CODPROVSNA = (is_array($LOCSNA) ? $LOCSNA[0] : null);
            $CODLOCSNA  = (is_array($LOCSNA) ? $LOCSNA[1] : null);
            $CUIT = str_replace('-', '', $c['CUIT']);
            $CUIT = substr($CUIT, 0, 2).'-'.substr($CUIT, 2, 8).'-'.substr($CUIT, -1);
            $CUIT = ($CUIT  == '--' ? '' : $CUIT);
            $params = array( 1 => array('Nombre' => 'RAZONSOCIAL','type' => 'CADENA50','value' => flxfn::escape($razon_social)),
                             2 => array('Nombre' => 'DIRECCION ','type' => 'VARCHAR15','value' => flxfn::escape($c['DIRECCION']) ),
                             3 => array('Nombre' => 'CODIGOPROVINCIA ','type' => 'VARCHAR15','value' => $id_provincia),
                             4 => array('Nombre' => 'LOCALIDAD ','type' => 'VARCHAR50','value' => flxfn::escape($c['LOCALIDAD']) ),
                             5 => array('Nombre' => 'BARRIO ','type' => 'VARCHAR200','value' => $MS_BARRIO),
                             6 => array('Nombre' => 'CODIGOZONA ','type' => 'VARCHAR15','value' => $MS_CODZONA),
                             7 => array('Nombre' => 'CODIGOUSUARIO ','type' => 'VARCHAR15','value' => $MS_USERERP),
                             8 => array('Nombre' => 'CODIGOCOBRADOR ','type' => 'VARCHAR15','value' => $MS_USERCOBRADOR),
                             9 => array('Nombre' => 'CODIGOACTIVIDAD ','type' => 'VARCHAR15','value' => $MS_CODACTIVIDAD),
                             10 => array('Nombre' => 'CODIGOLISTA ','type' => 'VARCHAR15','value' => $MS_LISTAPRECIO),
                             11 => array('Nombre' => 'CODIGOFORMADEPAGO ','type' => 'ENTERO','value' => $MS_CODFORMADEPAGO),
                             12 => array('Nombre' => 'CODPROVSNA ','type' => 'VARCHAR15','value' => $CODPROVSNA),
                             13 => array('Nombre' => 'CODLOCSNA ','type' => 'VARCHAR15','value' => $CODLOCSNA),
                             14 => array('Nombre' => 'TELEFONO ','type' => 'CADENA50','value' => $c['Telefono']),
                             15 => array('Nombre' => 'CELULAR ','type' => 'CADENA50','value' => $c['Celular']),
                             16 => array('Nombre' => 'CONDICIONIVA ','type' => 'CADENA15','value' => $condicionIva),
                             17 => array('Nombre' => 'DNI','type' => 'VARCHAR15','value' => substr($c['DNI'],0,8)),
                             18 => array('Nombre' => 'CUIT ','type' => 'varchar(13)','value' => $CUIT),
                             19 => array('Nombre' => 'COMENTARIOS ','type' => 'VARCHAR2000','value' => flxfn::escape($c['Comentarios']) ),
                             20 => array('Nombre' => 'EMAIL ','type' => 'VARCHAR100','value' => $c['Email']),
                             21 => array('Nombre' => 'CP ','type' => 'VARCHAR15','value' => $c['CP'])
                            );

            $SP_INSERTARCLIENTES = $ibase->exec_sp('MTO_INSERTARCLIENTES',$params,'varchar(50)','CODIGOPARTICULAR');
            if($GLOBALS['mododebug'] == 1)
                echo $SP_INSERTARCLIENTES;
            try{
              $result_clientes = $ibase->query($SP_INSERTARCLIENTES);
              $result_insertClientes = $ibase->fetch_object($result_clientes);
              if($result_insertClientes->CODIGOPARTICULAR != '') {
                 Db::getInstance()->insert('flx_cliente', array(
                  'ID_ERP' => $result_insertClientes->CODIGOPARTICULAR,
                  'id_prestashop' => $c['id_customer'],
                  'CODIGOIVA' => $MS_CONDICIONIVA,
                  'CODVENDEDOR' => $MS_USERERP
                 ));
                $RegistrosSincronizados = $RegistrosSincronizados + 1;
              }
            }catch(Exception $e){
               $message = $e->getMessage().' Linea error ('.$e->getLine().')';
               flxfn::addLog('Insertar Cliente', $message);
               $errMensaje = $errMensaje + 1;
               flxfn::sendMail('Error Sincronizacion: Insertar Cliente'._DB_NAME_,$content);
               continue;
            }
          
          $ibase->free_result($result_clientes);

  }//end Foreach

  flxfn::updateFechaUltimaSincro('MTO_CUSTOMER', $fechaInicioSincro,$ID_SHOP);
  flxfn::endProgressBar('Clientes -> MTO', $TOTALREG, $TOTALREG-$RegistrosSincronizados);
$msj = "<div class = 'line-result'><span class='count-badge'>".$RegistrosSincronizados."</span> CLIENTES</div>";
    if($RegistrosSincronizados == 0 || $GLOBALS['mododebug'] == 0)
    $msj=0;
flxfn::commitTransaction($msj);
$data['mensaje']['Clientes'] = $RegistrosSincronizados;
if($errMensaje > 0 && $GLOBALS['mododebug'] == 1)
$data['mensaje']['Clientes con Error'] = $errMensaje;
/* ----> FIN - INTEGRACION DE CLIENTES */



/*-----> INICIO - INTEGRACION DE CIENTES Y PEDIDOS */
   $RegistrosSincronizados = 0;$errMensaje = 0;
   $lastDate = date('Y-m-d',strtotime(flxfn::ultimaFechaSincro('MTO_ORDERS',0)));
   $sql_orders = "SELECT * FROM MTO_ORDERS('".$MS_CODOPERACION."','".$lastDate."')";
   $TOTALREG = flxfn::initProgressBar('Estados de Pedido -> MTO', $sql_orders, $position, (int)$mododebug, $ibase);
   $result_orders = $ibase->query($sql_orders);
   while ($row = $ibase->fetch_object($result_orders)):
        flxfn::updateProgressBar($position, $TOTALREG);
        $id_order = flxfn::equivalenciaID($row->NUMEROPEDIDO,'flx_pedido',true,true,true);
        $id_order_state = flxfn::equivalenciaID($row->ESTADO,'flx_estados_np');
        $ListIdERP = flxfn::getListIdERP($id_order);

        if($id_order != 'NULL'):
          $order = new Order((int)$id_order);
            if ((int)$id_order_state > 0 && empty($order->getHistory(1,$id_order_state))):
              $order->setCurrentState((int)$id_order_state);
              $order->setWsShippingNumber($row->NROCOMPROBANTE);
              if($row->ESTADO == 'ANULADA')
              {
                $where = " WHERE ID_ARTICULO in (".$ListIdERP.")";
                $sql_stock = flxfn::getSQLstock((int)$GLOBALS['USALOTE'],'02.01.1900',$MS_DEPOSITOS,$where);
                $result_stock = $ibase->query($sql_stock);
                  while ($row = $ibase->fetch_object($result_stock))
                  {
                    $id_producto = flxfn::equivalenciaID($row->ID_ARTICULO,'flx_p2p');

                    if($id_producto == 'NULL')
                      continue;

                    $id_producto_combination = ((int)$GLOBALS['USATALLE'] ? flxfn::equivalenciaID($row->ID_ARTICULO.$row->TALLE,'flx_pa2pa') : 0 );

                    $stock = ($MS_STOCKREAL ? $row->STOCKREAL : ($row->STOCKREMANENTE - $row->STOCKFACTSINREMITIR) );

                    StockAvailable::setQuantity($id_producto, $id_producto_combination,Tools::ceilf($stock));

                  }
                $ibase->free_result($result_stock);
                if(Parametros::get('MS_STOCKCERO'))
                    flxfn::updateActive($ID_SHOP,0);
                flxfn::updateActive($ID_SHOP,1);
              }
            endif;
        endif;
   endwhile;
   $ibase->free_result($result_orders);

   flxfn::updateFechaUltimaSincro('MTO_ORDERS', $fechaInicioSincro,0);
//traigo los pedidos de la tienda que no se sincronizaron
  $orders = flxfn::getLastOrder($MS_CODTRANSPORTISTA,$MS_LISTAPRECIO, $MS_CODOPERACION,$MS_ESTADOSPEDIDOS,$MS_MAYORISTA,$ID_SHOP);
  if($orders){
    foreach($orders as $pedidos => $pedido){

      $montoPedido =0;
      
      $tr = $ibase->trans();
      flxfn::startTransaction();

      try{
        $pedido['DESCUENTOGRAL'] = flxfn::getRuleDiscount($pedido['ordenTienda']);

        $SP_INICIALIZAR_PEDIDO = $ibase->exec_sp("MTO_INICIALIZAR_PEDIDO",'','varchar(50)','RESULTADO','Execute');
        $result_pedidos = $ibase->query($SP_INICIALIZAR_PEDIDO);
        if($result_pedidos === false){
            throw new Exception('MTO_INICIALIZAR_PEDIDO');
        }
        //nro de linea del pedido
        $linea = 1;
        //traigo el detalle de cada pedido
        $orders_details = flxfn::getOrderTalles($MS_TALLES, $pedido['ordenTienda']);
        foreach($orders_details as $order => $detail)
        {
            $TALLE = ($MS_TALLES == 1 ? $detail['Talle'] : '000');

            $listaPedido[$pedido['ordenTienda']] = flxfn::equivalenciaListaPedido($detail['CLIENTE']);

            if(flxfn::equivalenciaCondicionIva($detail['CLIENTE']))
              $CondicionIVA[$pedido['ordenTienda']] = flxfn::equivalenciaCondicionIva($detail['CLIENTE']);
            else
              $CondicionIVA[$pedido['ordenTienda']] = $MS_CONDICIONIVA;

            $montoiva1[$pedido['ordenTienda']] = $detail['MONTOIVA1'];

                        
            $precioUnitario = ($detail['Descuento']!=0 || $detail['DescuentoM']!=0  ? $detail['PrecioUnitario'] : $detail['price_original']);

            $precioUnitario = ($detail['montoii']!=0 ? $precioUnitario - $detail['montoii']  : $precioUnitario);

            $montoPedido += $precioUnitario * $detail['Cantidad'];
            
            //Ejecutamos al SP_CUERPOPEDIDOS
            $params = array( 1 => array('Nombre' => 'CODIGOPEDIDO','type' => 'CADENA50','value' => $pedido['ordenTienda']),
                             2 => array('Nombre' => 'LINEA','type' => 'CADENA50','value' => $linea),
                             3 => array('Nombre' => 'CODIGOARTICULO','type' => 'VARCHAR15','value' => $detail['CodigoArticulo']),
                             4 => array('Nombre' => 'CANTIDAD','type' => 'VARCHAR15','value' => $detail['Cantidad']),
                             5 => array('Nombre' => 'LOTE','type' => 'VARCHAR50','value' => $TALLE),
                             6 => array('Nombre' => 'DESCUENTO','type' => 'VARCHAR200','value' => $detail['Descuento']),
                             7 => array('Nombre' => 'PRECIOUNITARIO','type' => 'VARCHAR15','value' => $precioUnitario),
                             8 => array('Nombre' => 'CODIGODEPOSITO','type' => 'VARCHAR15','value' => $MS_DEPOSITOSSTOCK),
                            );
            $SP_CUERPOPEDIDOS = $ibase->exec_sp("MTO_CUERPOPEDIDOS",$params,"Varchar(50)","RESULTADO","Execute");

            if($GLOBALS['mododebug'] == 1)
                echo("<br/>linea---> ".$SP_CUERPOPEDIDOS);

            $result_sp = $ibase->query($SP_CUERPOPEDIDOS);

            if($result_sp === false){
                throw new Exception('MTO_CUERPOPEDIDOS Articulo');
            }

            $linea++;
            $ibase->free_result($result_sp);
        }//end foreach

        // Agrego la linea del costo del envio
        if($pedido['montoEnvio'] != 0)
        {
            $params = array( 1 => array('Nombre' => 'CODIGOPEDIDO','type' => 'CADENA50','value' => $pedido['ordenTienda']),
                            2 => array('Nombre' => 'LINEA','type' => 'CADENA50','value' => $linea),
                            3 => array('Nombre' => 'CODIGOARTICULO','type' => 'VARCHAR15','value' => $idFlete),
                            4 => array('Nombre' => 'CANTIDAD','type' => 'VARCHAR15','value' => '1'),
                            5 => array('Nombre' => 'LOTE','type' => 'VARCHAR50','value' => '000'),
                            6 => array('Nombre' => 'DESCUENTO','type' => 'VARCHAR200','value' => '0'),
                            7 => array('Nombre' => 'PRECIOUNITARIO','type' => 'VARCHAR15','value' => $pedido['montoEnvio']),
                            8 => array('Nombre' => 'CODIGODEPOSITO','type' => 'VARCHAR15','value' => $MS_DEPOSITOSSTOCK),
                        );
            $SP_CUERPOPEDIDOS_ENVIO = $ibase->exec_sp("MTO_CUERPOPEDIDOS",$params,"Varchar(50)","RESULTADO","Execute");

            if($GLOBALS['mododebug'] == 1)
                echo("<br/>linea envio---> ".$SP_CUERPOPEDIDOS_ENVIO);

            $result_spenvio = $ibase->query($SP_CUERPOPEDIDOS_ENVIO);

            if($result_spenvio === false){
              throw new Exception('MTO_CUERPOPEDIDOS Envio');
            }

            $ibase->free_result($result_spenvio);
            $linea++;
        }//end montoenvio

        //Lista de Precio
        $LISTAPRECIO = ($MS_MAYORISTA ? $listaPedido[$pedido['ordenTienda']] : $MS_LISTAPRECIO);
        // Codigo de Cliente ERP
        $CodigoCliente = flxfn::equivalenciaID((int)$pedido['CodigoCliente'],'flx_cliente',false);
        // Codigo Vendedor segun cliente
        $vendedor = ($MS_MAYORISTA ? flxfn::getVendedor($CodigoCliente) : $MS_USERERP);
        $observacion = flxfn::getValidestrDescription($pedido['Observaciones']);
        $menssage = flxfn::getMenssage($pedido['ordenTienda']);
        $mensaje = ( $menssage != '' ? $observacion.= ' | Mensaje: '.$menssage : $observacion );        
        //Ejecutamos SP_CABEZAPEDIDOS
        $params = array( 1 => array('Nombre' => 'CODIGOPEDIDO ','type' => 'CADENA50','value' => $pedido['ordenTienda']),
                         2 => array('Nombre' => 'CODIGOCLIENTE ','type' => 'CADENA50','value' => $CodigoCliente),
                         3 => array('Nombre' => 'FECHACOMPROBANTE  ','type' => 'VARCHAR15','value' => $pedido['FechaComprobante']),
                         4 => array('Nombre' => 'CODIGOUSUARIO  ','type' => 'VARCHAR15','value' => utf8_encode($vendedor)),
                         5 => array('Nombre' => 'OBSERVACIONES  ','type' => 'VARCHAR50','value' => flxfn::escape($mensaje)),
                         6 => array('Nombre' => 'LISTAPRECIO  ','type' => 'VARCHAR200','value' => $LISTAPRECIO),
                         7 => array('Nombre' => 'TIPOOPERACION  ','type' => 'VARCHAR15','value' => $pedido['TipoOperacion']),
                         8 => array('Nombre' => 'CODIGOMONEDA  ','type' => 'VARCHAR15','value' => $MS_CODMONEDA),
                         9 => array('Nombre' => 'DESCUENTOGRAL  ','type' => 'VARCHAR15','value' => $pedido['DESCUENTOGRAL']),
                         10 => array('Nombre' => 'CODIGOFORMADEPAGO ','type' => 'ENTERO','value' => $MS_CODFORMADEPAGO),
                         11 => array('Nombre' => 'DEPXCLI ','type' => 'ENTERO','value' => $MS_DEPXCLI)
                        );
        $SP_CABEZAPEDIDOS = $ibase->exec_sp("MTO_CABEZAPEDIDOS", $params, "FLOAT", 'RESULTADO');
        if($GLOBALS['mododebug'] == 1)
            echo("<br/>cabeza---> ".$SP_CABEZAPEDIDOS);
                        
        $result_spPedidos = $ibase->query($SP_CABEZAPEDIDOS);

        if($result_spPedidos === false)
          throw new Exception('MTO_CABEZAPEDIDOS');

        $result_pedido_ERP = $ibase->fetch_object($result_spPedidos);
        if($result_pedido_ERP === false)
          throw new Exception($ibase->getLastErrMsg());

          if(is_string($result_pedido_ERP->RESULTADO))
          {
            flxfn::breakTransaction($e->getMessage());
            $ibase->rollback($tr);
            throw new Exception('La condicion de IVA del cliente debe ser diferente de Consumidor Final cuando se informa CUIT.');

          }else{

                if($result_pedido_ERP->RESULTADO != '')
                {
                    //guardo el numero de pedido generado en Flexxus y su equivalencia con prestashop.
                    Db::getInstance()->insert('flx_pedido', array(
                      'ID_ERP' => $result_pedido_ERP->RESULTADO,
                      'id_prestashop' => $pedido['ordenTienda'],
                    ));

                    //create a new cURL resource
                    $ch = curl_init();

                    //setup request to send json via POST
                    
                    $jsonData = array(
                        'cliente' => Context::getContext()->shop->domain,
                        'monto' => $montoPedido
                    );

                    //Encode the array into JSON.
                    $jsonDataEncoded = json_encode($jsonData);

                    //Tell cURL that we want to send a POST request.
                    curl_setopt($ch, CURLOPT_POST, true);
                        
                    
                    curl_setopt($ch, CURLOPT_URL, "https://apitest.integrador.mitiendaonline.com:4001/pedido");

                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);

                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
                                        
                    //Attach our encoded JSON string to the POST fields.
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonDataEncoded);

                    curl_setopt($ch,CURLOPT_ENCODING, '');

                    //Set the content type to application/json
                    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));


                    //Execute the request
                    // $resultp = curl_exec($ch);

                    // //close cURL resource
                   
                    // echo $resultp;

                    if (!curl_exec($ch)) {
                        die('Error: "' . curl_error($ch) . '" - Code: ' . curl_errno($ch));
                    }
                    curl_close($ch);

                    $montoPedido = 0;

                }else{
                  throw new Exception($result_pedido_ERP);
                }

              $RegistrosSincronizados = $RegistrosSincronizados + 1;
              flxfn::commitTransaction(0);
              $ibase->commit($tr);
          }//END is_string($result_pedido_ERP->RESULTADO

          $ibase->free_result($result_spPedidos);

      }catch (Exception $e){


        if(strpos($e->getMessage(), 'El cliente del pedido no existe en Flexxus')){
            $success = flxfn::deleteFromFlxClient($pedido['CodigoCliente'], $codigoCliente);
            
            echo "<br>Se borro existosamente el cliente";
        }


            $message = $e->getMessage().' Linea error ('.$e->getLine().')';
            flxfn::addLog('Insertar Pedido', $message);

            $content = '<h2> Datos de Cliente</h2><br/>';
            $content.= 'Servidor: '._DB_SERVER_.'<br/>';
            $content.= 'Servidor: '._DB_NAME_.'<br/>';
            $content.= 'Error'.$e->getMessage().' Linea error ('.$e->getLine().')';
            flxfn::sendMail('Error Sincronizacion:'.$e->getMessage(), $content);

            if((bool)strpos($e->getMessage(), 'mismo CUIT/DNI.')) {
                $ID_CUSTOMER = flxfn::equivalenciaID($CodigoCliente,'flx_cliente');
                $customer = new Customer($ID_CUSTOMER);
                $content = '<h2> Atencion !!</h2><br/>';
                $content.= 'Un cliente nuevo (codigo Flx: '.$CodigoCliente.') quiere sincronizarse al sistema, pero se esta generando
                un error donde existe mas de un cliente con el mismo Cuit/DNI, porfavor verificar si el cliente existe en el sistema agregarle los siguientes datos.
                Razon Social:'.$customer->lastname.' '.$customer->firstname.'
                EMAIL:'.$customer->email.'';
                PrestaShopLogger::addLog($content, 1, null, 'Customer', (int)$customer->id, true, (int)$this->context->employee->id);
                flxfn::sendMail('Error de Cliente: ('.$CodigoCliente.') '._DB_NAME_, $content, true);
            }

            if((bool)strpos($e->getMessage(), 'condicion de IVA')) {
                $content = '<h2> Atencion !!</h2><br/>';
                $content.= 'Un cliente nuevo (codigo Flx: '.$CodigoCliente.') en la web acaba de realizar una compra 
                y se registró como Empresa para recibir Factura A o según corresponda por su condición de IVA.</br>
                El pedido en la web quedó pendiente para ser sincronizado al software de gestión 
                hasta que el Cliente sea editado en Flexxus y se le cambien la condición de IVA correspondiente.</br>
                Una vez que el cliente sea editado y deje de aparecer como Consumidor Final, el pedido se sincronizará y se generará la Nota de Pedido correspondiente en Flexxus.';
                PrestaShopLogger::addLog($content, 1, null, 'Order', 0, true, (int)$this->context->employee->id);
                flxfn::sendMail('Error de Pedido: '._DB_NAME_, $content, true);
            }

            flxfn::breakTransaction( $e->getMessage());
            $ibase->rollback($tr);            

      }//END TRYCATCH

    }//end Foreach

  }//End if orders 

  $msj="<br><div class='line-result'><span class='count-badge'>".$RegistrosSincronizados."</span> PEDIDOS</div>";
  if($RegistrosSincronizados == 0 || $GLOBALS['mododebug'] == 0)
  $msj = '';
  $data['mensaje']['Pedidos'] = $RegistrosSincronizados;
echo '<br><br>'.Tools::jsonEncode($data);
/*-----> FIN - INTEGRACION DE CIENTES Y PEDIDOS */

/* ----> INICIO - Integración de STOCK  */
$RegistrosSincronizados = 0;$errMensaje = 0;

if ($masiva == 1) {

    $sql_Stock = flxfn::getSQLstock((int)$GLOBALS['USALOTE'],"1900-01-01",$MS_DEPOSITOS);
    
}else {
    $sql_Stock = flxfn::getSQLstock((int)$GLOBALS['USALOTE'],flxfn::ultimaFechaSincro('MTO_STOCK',$ID_SHOP),$MS_DEPOSITOS);
}


$TOTALREG = flxfn::initProgressBar('Stock', $sql_Stock, $position, (int)$mododebug, $ibase);

$result_stock = $ibase->query($sql_Stock);
$stock='';$id_producto = '';
    try{

        if($result_stock === false){
            throw new Exception('MTO_STOCK');
        }

        while ($row = $ibase->fetch_object($result_stock)):

            flxfn::updateProgressBar($position, $TOTALREG);

            $id_producto = flxfn::equivalenciaID($row->ID_ARTICULO,'flx_p2p');

            if($id_producto == 'NULL')
              continue;

            if( flxfn::esPack($id_producto) == 1 )
                continue;

            $id_producto_combination = ((int)$GLOBALS['USATALLE'] || $TIPOFORM == 3 ? flxfn::equivalenciaID($row->ID_ARTICULO.$row->TALLE,'flx_pa2pa') : 0 );

            $stockRemanente = ($FACTURASINREMITIR ? $row->STOCKREMANENTE - $row->STOCKFACTSINREMITIR : $row->STOCKREMANENTE );
            $stock = ($MS_STOCKREAL ? $row->STOCKREAL : $stockRemanente );
            $stock = round($stock,4);

            
            if($MS_PERMITESTOCKNEGATIVO){

                if(flxfn::articuloStockNegativo($id_producto) == 1){
                    $stock = 10000;
                }

            }


            try{
                StockAvailable::setQuantity($id_producto, $id_producto_combination, Tools::ceilf($stock));
                $RegistrosSincronizados++;
            }catch(Exception $e){
                $message = $e->getMessage().' Linea error ('.$e->getLine().')';
                flxfn::addLog('Actualizar Stock', $message);
                $errMensaje = $errMensaje + 1;
                continue;
            }

            //Si el stock es positivo lo volvemos a reindexar al buscador.
            //Search::indexation(false,$producto->id);
            //StockAvailable::setQuantity($id_producto, $id_producto_combination,$stock);
        endwhile;

        if(Parametros::get('MS_STOCKCERO'))
        {
          //Desactivo todos los productos con stock menos cero
      		flxfn::updateActive($ID_SHOP,0);
        }
        //Activo todos los productos con stock mayor a cero
        if($MS_ACTIVACIONARTICULOS)
        flxfn::updateActive($ID_SHOP,1,$MS_ACTIVACIONIMAGEN);

        if($errMensaje == 0)
        flxfn::updateFechaUltimaSincro('MTO_STOCK', $fechaInicioSincro,$ID_SHOP);

    }catch(Exception $e){
        $message = $e->getMessage().' Linea error ('.$e->getLine().')';
        flxfn::addLog('Actualizar STOCK', $message);
    }

    $ibase->free_result($result_stock);

$msj = "<div class='line-result'><span class='count-badge'>".$RegistrosSincronizados."</span> STOCKS</div>";
if($RegistrosSincronizados == 0 || $GLOBALS['mododebug'] == 0)
    $msj=0;
flxfn::commitTransaction($msj);
flxfn::endProgressBar('stock', $TOTALREG, $errMensaje);
/* ----> FIN - Integracion de STOCK */