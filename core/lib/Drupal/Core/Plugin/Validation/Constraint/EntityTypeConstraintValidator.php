<?php

/**
 * @file
 * Contains \Drupal\Core\Plugin\Validation\Constraint\EntityTypeConstraintValidator.
 */

namespace Drupal\Core\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the EntityType constraint.
 */
class EntityTypeConstraintValidator extends ConstraintValidator {

  /**
   * Implements \Symfony\Component\Validator\ConstraintValidatorInterface::validate().
   */
  public function validate($typed_data, Constraint $constraint) {
    $entity = isset($typed_data) ? $typed_data->getValue() : FALSE;

    if (!empty($entity) && $entity->entityType() != $constraint->type) {
      $this->context->addViolation($constraint->message, array('%type', $constraint->type));
    }
  }
}
