<?php

namespace Tsrgtm\MediaLibrary\Enums;

enum MediaStatus: string
{
    case Uploading = 'uploading';
    case Uploaded = 'uploaded';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';
}
