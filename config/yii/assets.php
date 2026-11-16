<?php declare(strict_types=1);return [
'GET,HEAD pilot/assets/<path:(?:shlz|pilot|preopening|completion-register|address-register)\.css|favicon\.svg|sidebar-bootstrap\.js|navigation\.js|calendar\.js|shlz-behaviors\.js|preloader\.js|users\.js|object-queue\.js|inspection-schedule\.js|preopening\.js|template-offer\.js|checklist\.js|checklist-sw\.js|control-queue\.js|otiz\.js|address-register\.js|shlz-icons/(?:calendar-(?:sidebar|interface)|logout|search|cloud-upload|plus-alt-2|delivery-box|folder-file-open|chevron-(?:left|right)-duo|arrow-(?:left|right)-md|download|eye|close)\.svg|shlz-file-types/file-(?:pdf-default|doc|docx|xls|xlsx|img|generic)\.svg|fonts/golos-text-(?:cyrillic|latin)-(?:400|500|600)-normal\.woff2>'=>'pilot-asset/file',
'GET,HEAD help<slash:\/?>'=>'help/index',
'GET,HEAD help/index.html'=>'help/index',
'GET,HEAD help/<path:guide\.(?:css|js)|screenshots\/(?:01-login|02-objects|03-object-card|04-object-edit|05-team|06-installer-picker|07-calendar|08-dashboard|09-feedback|10-construction-control|11-checklist-mobile|12-otiz-register|14-original-form|15-team-opened)\.webp>'=>'help/file',
];
