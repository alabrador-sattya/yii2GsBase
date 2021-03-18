<?php

namespace sattya\GsBase;

/**
 * GsBase class file.
 *
 * @author Alfonso Labrador (alabrador@itsyx.com)
 * @copyright Copyright 2015 Sevilla
 *
 */
class gsbase
{
    const GSBASE_MAX_LENGTH = 1024;
    const GSBASE_MAX_ITERACIONES= 10000;
    const GSBASE_LOGIN_CMD = 'p_logon';
    const GSBASE_SEP = 2;
    const GSBASE_LOGIN_END_1 = 1;
    const GSBASE_LOGIN_END_2 = 2;
    const GSBASE_TIMEOUT = 300;

    public $__gsBase=null;

    /**
     * Inicia la conexión con GsBase.
     *
     * @param string $servidor ip del servidor GsBase
     * @param string $puerto puerto del servidor GsBase
     */
    public function gsbase_start($servidor,$puerto){
        global $__gsBase;
        $__gsBase=fsockopen($servidor,$puerto,$errno,$errstr);
        if(!$__gsBase){
                self::gsbase_error($errstr);
        }else{
            stream_set_timeout($__gsBase, self::GSBASE_TIMEOUT);
        }
    }

    /**
     * Conecta con GsBase. (Antes hay que realizar gsbase_start)
    */
    public function gsbase_conecta(){
        global $__gsBase;
        if(!$__gsBase) return(false);

        $msg='';
        $login_end='';
        $login_end=chr(self::GSBASE_LOGIN_END_1).chr(self::GSBASE_LOGIN_END_2);

        while(!($login_end == substr($msg,-2))){
                $msg .= fgets($__gsBase,2);
        }
        return(true);
    }

    protected function gsbase_error($msg,$nerr=500){
            self::gsbase_stop();
            //$nerr=($msg == 'Timeout')? 408 : ('Desbordamiento')? 413 : 500;
            throw new \yii\web\HttpException($nerr, 'Error GsBase: ' . $msg);
    }

    /**
     * Ejecuta accion en GsBase.
     *
     * @return mixed Respuesta de la accion en el servidor GsBase
     *
     * @param string $comando Acción a ejecutar
     * @param array $argumentos Array asociativo de tipo argumento=>valor a pasar a la acción
     * @param string $ventana Ventana en la que ejecutar la accion en GsBase (opcional)
     * @param string $salida
     */
    public function gsbase_exec($comando,$argumentos,$ventana='',&$salida=''){
            global $__gsBase;

            ini_set('memory_limit', '-1');
            ini_set('max_execution_time', 0);

            if($ventana!='') $comando.= '|'.$ventana;

            $comando=$comando.chr(self::GSBASE_SEP).$argumentos;
            $hex=dechex(strlen($comando));
            $hex=str_pad($hex,6, '0', STR_PAD_LEFT);
            fputs($__gsBase,$hex.$comando);
            $strlen=fgets($__gsBase,7);

            $info_sckt = stream_get_meta_data($__gsBase);
            if ($info_sckt['timed_out']) {
                    self::gsbase_error('Timeout',408);
                    return (false);
            }
            $len=hexdec($strlen);

            $iteraciones=0;
            $response='';
            $dyn_iteraciones=self::GSBASE_MAX_ITERACIONES;

            while($len>=1 && $iteraciones<self::GSBASE_MAX_ITERACIONES){
                    if($len<$dyn_iteraciones) $dyn_iteraciones=$len;
                    $str=fgets($__gsBase,$dyn_iteraciones+1);
                    $response.=$str;
                    $len=$len - strlen($str);
                    $iteraciones++;
            }

            if ($iteraciones >= self::GSBASE_MAX_ITERACIONES){
                self::gsbase_error('Respuesta demasiado larga. Acote sus peticiones',413);
                return (false);
            }

            $salida=$response;

            if ($ventana == '')
                return (true);
            else {
                $vector = explode(chr(self::GSBASE_SEP), $salida);
                if ($vector[1] != '') {
                    self::gsbase_error('EXEC:' . $vector[1]);
                    return (false);
                } else {
                    $res=unserialize($vector[0]);
                    if (isset($res[1]) && $res[1]=='TIMEOUT'){
                        self::gsbase_error('Time-Out',408);
                        return (false);
                    }else{
                        return ($vector[0]);
                    }
                }
            }
    }

    /**
     * Ejecuta Login en GsBase.
     *
     * @return boolean Indica si el login se ha realizado correctamente
     *
     * @param string $empresa Empresa en GsBase
     * @param array $usuario Usuario de GsBase
     * @param string $clave Contraseña de GsBase
     * @param string $aplicacion Aplicación de GsBase
     * @param string $ejercicio Ejercicio de GsBase
     * @param string $clave_aplicacion Clave de Aplicación (Opcional)
     * @param string $clave_ejercicio Clave de Ejercicio (Opcional)
     */
    public function gsbase_login($empresa,$usuario,$clave,$aplicacion,$ejercicio='',$clave_aplicacion='',$clave_ejercicio=''){
            if(!self::gsbase_exec(self::GSBASE_LOGIN_CMD,"$empresa,$usuario,$clave,$aplicacion,$ejercicio,$clave_aplicacion,$clave_ejercicio",'',$out)){
                    self::gsbase_error('p_login');
                    return(false);
            }
            if($out=='')
                    return(false);

            $vector=explode(chr(self::GSBASE_SEP),$out);
            switch($vector[0]){
                    case 'Ok':
                            $login_ok=true;
                            break;
                    default:
                            $login_ok=false;
                            self::gsbase_error('p_login:'.$vector[1]);
                            break;
            }

            return($login_ok);
    }

    /*
     * Finaliza la conexión con el servidor GsBase
     */
    public function gsbase_stop(){
            global $__gsBase;
            fclose($__gsBase);
    }

    //Funcion para devolver la respuesta formateada
    public function respuesta($res) {

            echo('<br>'); 
            echo('<strong>Resultado: </Strong>');
            print_r($res['0']);
            echo('<br>');
            echo('<strong>Valor: </Strong>');
            if (is_array($res['1'])){
                echo "<PRE>";
                print_r($res['1']);
                echo "</PRE>";
            }
            else{
                print_r($res['1']);
            }
            echo('<br>');
    }
}
?>
