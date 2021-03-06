<?php

/**
 * @file
 * Contains Drupal\paramconverter_test\TestControllers.
 */

namespace Drupal\paramconverter_test;

use Drupal\node\Plugin\Core\Entity\Node;

/**
 * Controller routine for testing the paramconverter.
 */
class TestControllers {

  public function testUserNodeFoo($user, $node, $foo) {
    $retval = "user: " . (is_object($user) ? $user->label() : $user);
    $retval .= ", node: " . (is_object($node) ? $node->label() : $node);
    $retval .= ", foo: " . (is_object($foo) ? $foo->label() : $foo);
    return $retval;
  }

  public function testNodeSetParent(Node $node, Node $parent) {
    return "Setting '{$parent->title}' as parent of '{$node->title}'.";
  }
}
