<?php

/*
 * Copyright 2011 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

class PhabricatorMarkupEngine {

  public static function extractPHIDsFromMentions(array $content_blocks) {
    $mentions = array();

    $engine = self::newDifferentialMarkupEngine();

    foreach ($content_blocks as $content_block) {
      $engine->markupText($content_block);
      $phids = $engine->getTextMetadata(
        'phabricator.mentioned-user-phids',
        array());
      $mentions += $phids;
    }

    return $mentions;
  }

  public static function newManiphestMarkupEngine() {
    return self::newMarkupEngine(array(
    ));
  }

  public static function newPhrictionMarkupEngine() {
    return self::newMarkupEngine(array(
      // Disable image macros on the wiki since they're less useful, we don't
      // cache documents, and the module is prohibitively expensive for large
      // documents.
      'macros' => false,
    ));
  }

  public static function newDifferentialMarkupEngine() {
    return self::newMarkupEngine(array(
      'custom-inline' => PhabricatorEnv::getEnvConfig(
        'differential.custom-remarkup-rules'),
      'custom-block'  => PhabricatorEnv::getEnvConfig(
        'differential.custom-remarkup-block-rules'),
    ));
  }

  public static function newProfileMarkupEngine() {
    return self::newMarkupEngine(array(
    ));
  }

  public static function newSlowvoteMarkupEngine() {
    return self::newMarkupEngine(array(
    ));
  }

  private static function getMarkupEngineDefaultConfiguration() {
    return array(
      'pygments'      => PhabricatorEnv::getEnvConfig('pygments.enabled'),
      'fileproxy'     => PhabricatorEnv::getEnvConfig('files.enable-proxy'),
      'youtube'       => PhabricatorEnv::getEnvConfig(
        'remarkup.enable-embedded-youtube'),
      'custom-inline' => array(),
      'custom-block'  => array(),
      'macros'        => true,
    );
  }

  private static function newMarkupEngine(array $options) {

    $options += self::getMarkupEngineDefaultConfiguration();

    $engine = new PhutilRemarkupEngine();

    $engine->setConfig('preserve-linebreaks', true);
    $engine->setConfig('pygments.enabled', $options['pygments']);

    $rules = array();
    $rules[] = new PhutilRemarkupRuleEscapeRemarkup();
    if ($options['fileproxy']) {
      $rules[] = new PhabricatorRemarkupRuleProxyImage();
    }

    if ($options['youtube']) {
      $rules[] = new PhabricatorRemarkupRuleYoutube();
    }

    $rules[] = new PhutilRemarkupRuleHyperlink();

    $rules[] = new PhabricatorRemarkupRuleDifferential();
    $rules[] = new PhabricatorRemarkupRuleDiffusion();
    $rules[] = new PhabricatorRemarkupRuleManiphest();
    $rules[] = new PhabricatorRemarkupRulePaste();

    if ($options['macros']) {
      $rules[] = new PhabricatorRemarkupRuleImageMacro();
    }

    $rules[] = new PhabricatorRemarkupRuleMention();
    $rules[] = new PhabricatorRemarkupRulePhriction();

    $custom_rule_classes = $options['custom-inline'];
    if ($custom_rule_classes) {
      foreach ($custom_rule_classes as $custom_rule_class) {
        PhutilSymbolLoader::loadClass($custom_rule_class);
        $rules[] = newv($custom_rule_class, array());
      }
    }

    $rules[] = new PhutilRemarkupRuleEscapeHTML();
    $rules[] = new PhutilRemarkupRuleMonospace();
    $rules[] = new PhutilRemarkupRuleBold();
    $rules[] = new PhutilRemarkupRuleItalic();

    $blocks = array();
    $blocks[] = new PhutilRemarkupEngineRemarkupQuotesBlockRule();
    $blocks[] = new PhutilRemarkupEngineRemarkupHeaderBlockRule();
    $blocks[] = new PhutilRemarkupEngineRemarkupListBlockRule();
    $blocks[] = new PhutilRemarkupEngineRemarkupCodeBlockRule();
    $blocks[] = new PhutilRemarkupEngineRemarkupDefaultBlockRule();

    $custom_block_rule_classes = $options['custom-block'];
    if ($custom_block_rule_classes) {
      foreach ($custom_block_rule_classes as $custom_block_rule_class) {
        PhutilSymbolLoader::loadClass($custom_block_rule_class);
        $blocks[] = newv($custom_block_rule_class, array());
      }
    }

    foreach ($blocks as $block) {
      if (!($block instanceof PhutilRemarkupEngineRemarkupCodeBlockRule)) {
        $block->setMarkupRules($rules);
      }
    }

    $engine->setBlockRules($blocks);

    return $engine;
  }

}
