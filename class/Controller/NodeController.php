<?php

namespace Anaplam\Controller;

use Anaplam\Kubernetes;
use Anaplam\Utility as Util;

class NodeController
{
    /**
     * protected k8s
     *
     */
    protected $k8s;

    /**
     * construct
     *
     */
    public function __construct()
    {
        $this->k8s = new Kubernetes();
    }

    /**
     * v1 get nodes
     *
     * @param $request
     * @param $response
     * @param $args
     *
     */
    public function v1GetNodes($request, $response, $args)
    {
        $msg = 'get nodes begin';
        Util::syslog($msg);
        $nodes = $this->k8s->getNodes();
        if (empty($nodes)) {
            $msg = 'get nodes nodes not found';
            echo yaml_emit(array('errors' => array($msg)));
            // syslog
            Util::syslog($msg);
            return;
        }
        foreach ($nodes as $node) {
            echo yaml_emit(json_decode($node->getSchema(), true));
        }
        // syslog
        $msg = 'get nodes end';
        Util::syslog($msg);
        return;
    }

    /**
     * v1 get node
     *
     * @param $request
     * @param $response
     * @param $args
     *
     */
    public function v1GetNode($request, $response, $args)
    {
        $location = $args['location'];
        $msg = 'get node begin"';
        Util::syslog($msg);
        $node = $this->k8s->getNode($location);
        if (empty($node)) {
            $msg = 'get node \'' . $location . '\' not found.';
            echo yaml_emit(array('errors' => array($msg)));
            // syslog
            Util::syslog($msg);
            return;
        }
        echo yaml_emit(json_decode($node->getSchema(), true));
        // syslog
        $msg = 'get node end';
        Util::syslog($msg);
        return;
    }
}

