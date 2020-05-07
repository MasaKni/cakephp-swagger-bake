<?php

namespace SwaggerBake\Lib;

use SwaggerBake\Lib\Annotation as SwagAnnotation;
use SwaggerBake\Lib\OpenApi\PathSecurity;

class Security extends AbstractParameter
{
    /**
     * @return PathSecurity[]
     */
    public function getPathSecurity() : array
    {
        $return = [];

        foreach ($this->getMethods() as $method) {
            $annotations = $this->reader->getMethodAnnotations($method);
            if (empty($annotations)) {
                continue;
            }

            foreach ($annotations as $annotation) {
                if ($annotation instanceof SwagAnnotation\SwagSecurity) {
                    $return = array_merge(
                        $return,
                        [(new SwagAnnotation\SwagSecurityHandler())->getPathSecurity($annotation)]
                    );
                }
            }
        }

        return $return;
    }
}