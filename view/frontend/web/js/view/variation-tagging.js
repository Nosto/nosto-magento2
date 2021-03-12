/** @noinspection DuplicatedCode */
/*
 * Copyright (c) 2020, Nosto Solutions Ltd
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 * this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright notice,
 * this list of conditions and the following disclaimer in the documentation
 * and/or other materials provided with the distribution.
 *
 * 3. Neither the name of the copyright holder nor the names of its contributors
 * may be used to endorse or promote products derived from this software without
 * specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
 * ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * @author Nosto Solutions Ltd <contact@nosto.com>
 * @copyright 2020 Nosto Solutions Ltd
 * @license http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause
 *
 */

// noinspection JSUnresolvedFunction
define([
  'uiComponent',
  'Magento_Customer/js/customer-data',
  'nostojs'
], function (Component, customerData, nostojs) {
  'use strict';

  // noinspection JSUnusedGlobalSymbols,JSCheckFunctionSignatures
  return Component.extend({
    initialize: function () {
      // noinspection JSUnresolvedFunction
      this._super();
      //noinspection JSUnusedGlobalSymbols
      this.variationTagging = customerData.get('active-variation-tagging');
    },
    reloadRecommendations: function () {
      // Remove the static variation if it exists - it should not but as a safeguard we rename the class
      const element = document.querySelector(".nosto_variation");
      if (element) {
        element.classList.remove('nosto_variation');
        element.classList.add('nosto_variation_static');
      }
      document.querySelector(".nosto_variation_dynamic").classList.add("nosto_variation")
      if (typeof nostojs === 'function') {
        nostojs(function (api) {
          // noinspection JSUnresolvedFunction
          api.loadRecommendations();
        });
      }
    }
  });
});
