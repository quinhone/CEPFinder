<?php

namespace GustavoFenilli\CEPFinder;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;

/**
 * Class CEPFinder
 * @package GustavoFenilli\CEPFinder
 */
class CEPFinder
{
    /**
     * @var Client
     */
    private $client;

    /**
     * @var array
     */
    private $apiResult;

    /**
     * Constante de URL da API do ViaCEP
     * 
     * @const API_URL
     */
    const API_URL = "https://viacep.com.br/ws/{cep}/json/";

    /**
     * @var null|string
     */
    protected $cep;

    /**
     * @var string
     */
    protected $logradouro;

    /**
     * @var string
     */
    protected $complemento;

    /**
     * @var string
     */
    protected $bairro;

    /**
     * @var string
     */
    protected $localidade;

    /**
     * @var string
     */
    protected $uf;

    /**
     * @var string
     */
    protected $unidade;

    /**
     * @var string
     */
    protected $ibge;

    /**
     * @var string
     */
    protected $gia;

    /**
     * CEPFinder constructor.
     * @param null|string $cep
     */
    public function __construct($cep = null)
    {
        $this->cep = $cep;

        $this->client = new Client();
    }

    /**
     * @param null|string $cep
     * 
     * @return int
     * 
     * @throws \Exception
     */
    public function consult($cep = null)
    {
        if ($cep != null) {
            $cepEscoped = $cep;
        } else {
            $cepEscoped = $this->cep;
        }

        if (!$cepEscoped) {
            throw new \Exception("CEP não informado.");
        }

        try {
            $result = $this->client->get(str_replace("{cep}", $cepEscoped, self::API_URL));
        } catch (GuzzleException $exception) {
            return $exception->getResponse()->getStatusCode();
        }

        $response = json_decode($result->getBody(), true);

        foreach ($response as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }

        $this->setArrayByAttributes();

        return $result->getStatusCode();
    }

    /**
     * @return false|string
     */
    public function getResultJson()
    {
        return json_encode($this->apiResult);
    }

    /**
     * @return \SimpleXMLElement
     */
    public function getResultXML()
    {
        $xml = new \SimpleXMLElement("<?xml version=\"1.0\"?><localidade></localidade>");
        $this->arrayToXml($this->apiResult, $xml);

        return $xml;
    }

    /**
     * @param string    $name
     * @param mixed     $value
     * 
     * @throws \Exception
     */
    public function __set($name, $value)
    {
        if (!property_exists($this, $name)) {
            throw new \Exception("Propriedade $name não existe");
        }

        parent::__set();
    }

    /**
     * @param string    $name
     * 
     * @throws \Exception
     */
    public function __get($name)
    {
        if (!property_exists($this, $name)) {
            throw new \Exception("Propriedade $name não existe");
        }

        parent::__get();
    }

    /**
     * @param string    $method
     * @param array     $arguments
     * 
     * @throws \Exception
     */
    public function __call($method, $arguments)
    {
        $resultPreg = $this->getArrayFromMethodCall($method);

        if (!sizeof($resultPreg) > 1) {
            throw new \Exception("Método $method não existe");
        }

        if ($resultPreg[0] == 'get') {
            return $this->getAttribute($resultPreg[1]);
        } else {
            if ($resultPreg[0] == 'set') {
                return $this->setAttribute($resultPreg[1], $arguments);
            }
        }

        throw new \Exception("Método $method não existe");
    }

    /**
     * @param string    $method
     * 
     * @return array
     */
    private function getArrayFromMethodCall($method)
    {
        return explode(' ', preg_replace("/(([a-z])([A-Z])|([A-Z])([A-Z][a-z]))/", "\\2\\4 \\3\\5", $method));
    }

    /**
     * @param string    $attribute
     * 
     * @return mixed
     */
    private function getAttribute($attribute)
    {
        $attribute = $this->lowerCaseAttribute($attribute);
        return $this->{$attribute};
    }

    /**
     * @param string    $attribute
     * @param array     $arguments
     * 
     * @throws \Exception
     */
    private function setAttribute($attribute, $arguments)
    {
        if (sizeof($arguments) > 1) {
            throw new \Exception("Este método só aceita um argumento");
        }

        $value = $arguments[0];
        $attribute = $this->lowerCaseAttribute($attribute);
        $this->{$attribute} = $value;
        $this->apiResult[$attribute] = $this->{$attribute};
    }

    /**
     * Metodo para construir o array de respostas usando as propriedades da classes
     */
    private function setArrayByAttributes()
    {
        $this->apiResult = [
            'cep' => $this->cep,
            'logradouro' => $this->logradouro,
            'complemento' => $this->complemento,
            'bairro' => $this->bairro,
            'localidade' => $this->localidade,
            'uf' => $this->uf,
            'unidade' => $this->unidade,
            'ibge' => $this->ibge,
            'gia' => $this->gia
        ];
    }

    /**
     * @param string    $attribute
     * 
     * @return string
     */
    private function lowerCaseAttribute($attribute)
    {
        return mb_strtolower($attribute);
    }

    /**
     * @param array             $array
     * @param \SimpleXMLElement $xml
     */
    private function arrayToXml($array, &$xml)
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                if (!is_numeric($key)) {
                    $subnode = $xml->addChild("$key");
                    $this->arrayToXml($value, $subnode);
                } else {
                    $subnode = $xml->addChild("item$key");
                    $this->arrayToXml($value, $subnode);
                }
            } else {
                $xml->addChild("$key", htmlspecialchars("$value"));
            }
        }
    }
}