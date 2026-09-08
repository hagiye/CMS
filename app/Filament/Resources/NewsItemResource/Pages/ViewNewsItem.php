<?php

namespace App\Filament\Resources\NewsItemResource\Pages;

use App\Filament\Resources\NewsItemResource;
use Filament\Resources\Pages\ViewRecord;

class ViewNewsItem extends ViewRecord
{
    protected static string $resource = NewsItemResource::class;

    protected static ?string $title = 'View News Item';

}
