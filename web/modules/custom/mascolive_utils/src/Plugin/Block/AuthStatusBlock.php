<?php

namespace Drupal\mascolive_utils\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Muestra el estado de autenticación: botón "Iniciar sesión" (anónimo) o
 * "Hola, {nombre}" + "Cerrar sesión" (autenticado).
 *
 * @Block(
 *   id = "mascolive_auth_status",
 *   admin_label = @Translation("Estado de autenticación MascoLive"),
 * )
 */
class AuthStatusBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $account = \Drupal::currentUser();

    if ($account->isAnonymous()) {
      return [
        '#type' => 'link',
        '#title' => t('Iniciar sesión'),
        '#url' => Url::fromRoute('user.login'),
        '#attributes' => [
          'class' => ['mascolive-auth', 'mascolive-auth--login'],
        ],
      ];
    }

    return [
      '#theme' => 'item_list',
      '#items' => [
        '#markup' => t('Hola, @name', ['@name' => $account->getDisplayName()]),
        Link::fromTextAndUrl(t('Cerrar sesión'), Url::fromRoute('user.logout'))->toString(),
      ],
      '#attributes' => ['class' => ['mascolive-auth']],
    ];
  }

}