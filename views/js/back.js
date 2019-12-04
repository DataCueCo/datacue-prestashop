/**
 * MIT License
 * Copyright (c) 2019 DataCue
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 *  @author    DataCue <contact@datacue.co>
 *  @copyright 2019 DataCue
 *  @license   https://opensource.org/licenses/MIT MIT License
 */

function getSyncStatus() {
  $.ajax({
    url: syncStatusUrl,
    type: 'GET',
    dataType: 'json'
  }).success(function(data) {
    var html = '';
    Object.keys(data).forEach(function(key) {
      html += '<tr>';
      html += '<td align="center">' + key + '</td>';
      html += '<td align="center">' + data[key].total + '</td>';
      html += '<td align="center">' + (data[key].total - data[key].completed - data[key].failed) + '</td>';
      html += '<td align="center">' + data[key].completed + '</td>';
      html += '<td align="center">' + data[key].failed + '</td>';
      html += '</tr>';
    });
    $("#datacue-sync-status-table tbody").html(html);
  });
}

function getLogOfDate(date) {
  $("#datacue-log-frame").attr('src', window.logUrlPrefix + 'datacue-' + date + '.log');
}

$(document).ready(function() {
  getSyncStatus();

  setInterval(function() {
    getSyncStatus();
  }, 30000);

  var currentDate = $("#datacue-logs-date-select").val();
  if (currentDate && currentDate != '') {
    getLogOfDate(currentDate);
  }

  $("#datacue-logs-date-select").change(function() {
    getLogOfDate($("#datacue-logs-date-select").val());
  });

  $("#btn-disconnect").on("click", function() {
    $("#dialog-disconnect").removeClass("hide");
  });

  $("#disconnect-ok").on("click", function() {
    $.ajax({
      url: disconnectUrl,
      type: 'POST',
      dataType: 'json',
    }).done(function () {
      $("#dialog-disconnect").addClass("hide");
      window.location.href = window.location.href;
    });
  });

  $("#disconnect-cancel").on("click", function() {
    $("#dialog-disconnect").addClass("hide");
  });
});
