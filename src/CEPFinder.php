<?php

namespace GustavoFenilli\CEPFinder;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;

class CEPFinder
{
    private $client;
    private $apiResult;
    const API_URL = "https://viacep.com.br/ws/{cep}/json/";

    protected $cep;
    protected $logradouro;
    protected $complemento;
    protected $bairro;
    protected $localidade;
    protected $uf;
    protected $unidade;
    protected $ibge;
    protected $gia;

    public function __construct($cep = null){
        $this->cep = $cep;

        $this->client = new Client();
    }

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

        foreach($response as $key => $value){
            if(property_exists($this, $key)){
                $this->$key = $value;
            }
        }

        $this->setArrayByAttributes();

        return $result->getStatusCode();
    }

    public function getResultJson()
    {
        return json_encode($this->apiResult);
    }

    public function getResultXML()
    {
        $xml = new \SimpleXMLElement("<?xml version=\"1.0\"?><localidade></localidade>");
        $this->arrayToXml($this->apiResult, $xml);

        return $xml;
    }

    public function __set($name, $value)
    {
        if (!property_exists($this, $name)) {
            throw new \Exception("Propriedade $name não existe");
        }

        parent::__set();
    }

    public function __get($name)
    {
        if (!property_exists($this, $name)) {
            throw new \Exception("Propriedade $name não existe");
        }

        parent::__get();
    }

    public function __call($method, $arguments)
    {
        $resultPreg = $this->getArrayFromMethodCall($method);

        if (!sizeof($resultPreg) > 1) {
            throw new \Exception("Método $method não existe");
        }

        if ($resultPreg[0] == 'get') {
            return $this->getAttribute($resultPreg[1]);
        } else if ($resultPreg[0] == 'set') {
            return $this->setAttribute($resultPreg[1], $arguments);
        }

        throw new \Exception("Método $method não existe");
    }

    private function getArrayFromMethodCall($method)
    {
        return explode(' ', preg_replace("/(([a-z])([A-Z])|([A-Z])([A-Z][a-z]))/", "\\2\\4 \\3\\5", $method));
    }

    private function getAttribute($attribute)
    {
        $attribute = $this->lowerCaseAttribute($attribute);
        return $this->{$attribute};
    }

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

    private function lowerCaseAttribute($attribute)
    {
        return mb_strtolower($attribute);
    }

    private function arrayToXml($array, &$xml)
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                if (!is_numeric($key)) {
                    $subnode = $xml->addChild("$key");
                    $this->arrayToXml($value, $subnode);
                }else{
                    $subnode = $xml->addChild("item$key");
                    $this->arrayToXml($value, $subnode);
                }
            } else {
                $xml->addChild("$key", htmlspecialchars("$value"));
            }
        }
    }
}