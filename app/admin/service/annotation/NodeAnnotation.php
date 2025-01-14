<?php

namespace app\admin\service\annotation;

use Doctrine\Common\Annotations\Annotation\Attributes;
use Attribute;

/**
 * action 节点注解类
 */
#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_METHOD)]
final class NodeAnnotation
{
    /** 过滤节点 */
    const IGNORE_NODE = 'NODE';

    /**
     * @param string $title
     * @param bool $auth 是否需要权限
     * @param string|array $ignore
     */
    public function __construct(public string $title = '', public bool $auth = true, public string|array $ignore = '')
    {
    }

}