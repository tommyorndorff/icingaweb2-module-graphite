<?php

namespace Icinga\Module\Graphite;

use Icinga\Application\Config;
use Icinga\Exception\ConfigurationError;
use Icinga\Module\Monitoring\Object\MonitoredObject;
use Icinga\Module\Monitoring\Object\Host;
use Icinga\Module\Monitoring\Object\Service;
use Icinga\Module\Monitoring\Plugin\PerfdataSet;
use Icinga\Web\Hook\GrapherHook;
use Icinga\Web\Url;

class Grapher extends GrapherHook
{
    protected $hasPreviews = true;
    protected $hasTinyPreviews = true;
    protected $graphiteConfig;
    protected $baseUrl = 'http://graphite.com/render/?';
    protected $metricPrefix = 'icinga';
    protected $serviceMacro = '$HOSTNAME$.$SERVICEDESC$';
    protected $hostMacro = '$HOSTNAME$';

    protected function init()
    {
        $cfg = Config::module('graphite')->getSection('graphite');
        $this->baseUrl = rtrim($cfg->get('base_url', $this->baseUrl), '/');
        $this->metricPrefix = $cfg->get('metric_prefix', $this->metricPrefix);
        $this->serviceMacro = $cfg->get('service_name_template', $this->serviceMacro);
        $this->hostMacro = $cfg->get('host_name_template', $this->hostMacro);
    }

    public function has(MonitoredObject $object)
    {
        if ($object instanceof Host) {
            $service = '_HOST_';
        } elseif ($object instanceof Service) {
            $service = $object->service_description;
        } else {
            return false;
        }

        return true;
    }

    public function getPreviewHtml(MonitoredObject $object)
    {
        $object->fetchCustomvars();
        if (array_key_exists("Graphite Keys", $object->customvars))
            $graphiteKeys = $object->customvars["Graphite Keys"];
        else {
            $graphiteKeys = array();
            foreach (PerfdataSet::fromString($object->perfdata)->asArray() as $pd)
                $graphiteKeys[] = $pd->getLabel();
        }

        if ($object instanceof Host) {
            $host = $object;
            $service = null;
        } elseif ($object instanceof Service) {
            $service = $object;
            $host = null;
        } else {
            return '';
        }

        $html = "<table class=\"avp newsection\">\n"
               ."<tbody>\n";

        foreach ($graphiteKeys as $metric) {
            $html .= "<tr><th>\n"
                  . "$metric\n" 
                  . '</th><td>'
                  . $this->getPreviewImage($host, $service, $metric)
                  . "</td>\n"
                  . "<tr>\n";
        }

        $html .= "</tbody></table>\n";
        return $html;
    }

    // Currently unused,
    public function getSmallPreviewImage($host, $service = null)
    {       
        return null;
    }

    private function getPreviewImage($host, $service, $metric)
    {        
      
        if ($host != Null){
            $target = Macro::resolveMacros($this->hostMacro, $host);
        } elseif  ($service != null ){
            $target = Macro::resolveMacros($this->serviceMacro, $service);
        } else {
           $target = '';
        }

        $target .= '.'. Macro::escapeMetric($metric, true);


        $imgUrl = sprintf(
            '%s&target=%s.%s&source=0&width=300&height=120&hideAxes=true&lineWidth=2&hideLegend=true&colorList=049BAF',
            $this->baseUrl,
            $this->metricPrefix,
            $target
        );

        $url = Url::fromPath('graphite', array(
            'target' => urlencode($target),
            'base_url' => urlencode($this->baseUrl),
            'metric_prefix' => urlencode($this->metricPrefix)
        ));

        $html = '<a href="%s" title="%s"><img src="%s" alt="%s" width="300" height="120" /></a>';

        return sprintf(
            $html,
            $url,
            $metric,
            $imgUrl,
            $metric
       );
    }
}
