<?php

namespace Graphp\GraphML;

use Fhaculty\Graph\Exporter\ExporterInterface;
use Fhaculty\Graph\Graph;
use JsonSerializable;
use RuntimeException;
use SimpleXMLElement;
use Fhaculty\Graph\Edge\Directed;

class Exporter implements ExporterInterface
{
    /** @internal */
    const SKEL = <<<EOL
<?xml version="1.0" encoding="UTF-8"?>
<graphml xmlns="http://graphml.graphdrawing.org/xmlns"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://graphml.graphdrawing.org/xmlns
     http://graphml.graphdrawing.org/xmlns/1.0/graphml.xsd">
</graphml>
EOL;

    const GRAPHML_ATTRIBUTE_TYPE_BOOLEAN = 'boolean';
    const GRAPHML_ATTRIBUTE_TYPE_INT = 'int';
    const GRAPHML_ATTRIBUTE_TYPE_LONG = 'long';
    const GRAPHML_ATTRIBUTE_TYPE_FLOAT = 'float';
    const GRAPHML_ATTRIBUTE_TYPE_DOUBLE = 'double';
    const GRAPHML_ATTRIBUTE_TYPE_STRING = 'string';

    const GRAPHML_ATTRIBUTE_FOR_GRAPH = 'graph';
    const GRAPHML_ATTRIBUTE_FOR_NODE = 'node';
    const GRAPHML_ATTRIBUTE_FOR_EDGE = 'edge';
    const GRAPHML_ATTRIBUTE_FOR_ALL = 'all';

    /** @var array GRAPHML_ATTRIBUTE_TYPE_MAP Map PHP scalar types to GraphML attribute types */
    const GRAPHML_ATTRIBUTE_TYPE_MAP = [
        'boolean' => self::GRAPHML_ATTRIBUTE_TYPE_BOOLEAN,
        'integer' => self::GRAPHML_ATTRIBUTE_TYPE_INT,
        'double' => self::GRAPHML_ATTRIBUTE_TYPE_FLOAT,
        'string' => self::GRAPHML_ATTRIBUTE_TYPE_STRING,

        // No PHP equivalents
        // '' => self::GRAPHML_ATTRIBUTE_TYPE_DOUBLE,
        // '' => self::GRAPHML_ATTRIBUTE_TYPE_DOUBLE,
        // '' => self::GRAPHML_ATTRIBUTE_TYPE_LONG,
    ];

    private static function _getGraphMLTypeForAttributeValue($value): string {
        $phpType = gettype($value);
        if (!isset(self::GRAPHML_ATTRIBUTE_TYPE_MAP[$phpType])) {
            kgo_dump($value);
            throw new RuntimeException("Unable to convert PHP type '{$phpType}' to GraphML type.");
        }

        return self::GRAPHML_ATTRIBUTE_TYPE_MAP[$phpType];
    }

    /**
     * Exports the given graph instance.
     *
     * ```php
     * $graph = new Fhaculty\Graph\Graph();
     *
     * $a = $graph->createVertex('a');
     * $b = $graph->createVertex('b');
     * $a->createEdgeTo($b);
     *
     * $exporter = new Graphp\GraphML\Exporter();
     * $data = $exporter->getOutput($graph);
     *
     * file_put_contents('example.graphml', $data);
     * ```
     *
     * This method only supports exporting the basic graph structure, with all
     * vertices and directed and undirected edges.
     *
     * Note that none of the a"advanced concepts" of GraphML (Nested Graphs, Hyperedges and Ports) are
     * currently implemented. We welcome PRs!
     *
     * @param Graph $graph
     * @return string
     */
    public function getOutput(Graph $graph)
    {
        $root = new SimpleXMLElement(self::SKEL);

        $graphElem = $root->addChild('graph');
        $graphElem['edgeDefault'] = 'undirected';

        $attributeDefinitions = [];

        foreach ($graph->getVertices()->getMap() as $id => $vertex) {
            /* @var $vertex \Fhaculty\Graph\Vertex */
            $vertexElem = $graphElem->addChild('node');
            $vertexElem['id'] = $id;
            $vertexElem['label'] = $id;

            $vertexAttributeBag = $vertex->getAttributeBag();
            foreach ($vertexAttributeBag->getAttributes() as $attributeId => $attributeValue) {
                if ($attributeValue !== null) {
                    if ($attributeValue instanceof JsonSerializable) {
                        $attributeValue = json_encode($attributeValue);
                    }
                    $attributeType = self::_getGraphMLTypeForAttributeValue($attributeValue);

                    $dataElem = $vertexElem->addChild('data', $attributeValue);
                    $dataElem->addAttribute('key', $attributeId);

                    if (!isset($attributeDefinitions[$attributeId])) {
                        $attributeDefinitions[$attributeId] = [
                            'for' => self::GRAPHML_ATTRIBUTE_FOR_NODE,
                            'attr.type' => $attributeType,
                        ];
                    }
                }
            }
        }

        foreach ($graph->getEdges() as $edge) {
            /* @var $edge \Fhaculty\Graph\Edge\Base */
            $edgeElem = $graphElem->addChild('edge');
            $edgeElem['source'] = $edge->getVertices()->getVertexFirst()->getId();
            $edgeElem['target'] = $edge->getVertices()->getVertexLast()->getId();
            $vertexElem['label'] = $edgeElem['source'].' to '. $edgeElem['target'];

            if ($edge instanceof Directed) {
                $edgeElem['directed'] = 'true';
            }

            $edgeAttributeBag = $edge->getAttributeBag();
            foreach ($edgeAttributeBag->getAttributes() as $attributeId => $attributeValue) {
                if ($attributeValue !== null) {
                    if ($attributeValue instanceof JsonSerializable) {
                        $attributeValue = json_encode($attributeValue);
                    }
                    $attributeType = self::_getGraphMLTypeForAttributeValue($attributeValue);

                    $dataElem = $edgeElem->addChild('data', $attributeValue);
                    $dataElem->addAttribute('key', $attributeId);

                    if (!isset($attributeDefinitions[$attributeId])) {
                        $attributeDefinitions[$attributeId] = [
                            'for' => self::GRAPHML_ATTRIBUTE_FOR_EDGE,
                            'attr.type' => $attributeType,
                        ];
                    }
                }
            }
        }

        foreach ($attributeDefinitions as $attributeId => $attributeDefinition) {
            $attributeElem = $root->addChild('key');
            $attributeElem['id'] = $attributeId;
            $attributeElem['attr.name'] = $attributeId; // intentionally the same as `id`
            $attributeElem['attr.type'] = $attributeDefinition['attr.type'];
            $attributeElem['for'] = $attributeDefinition['for'];
        }

        return $root->asXML();
    }
}
