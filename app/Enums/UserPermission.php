<?php

namespace App\Enums;

enum UserPermission: string
{
    case ManageCompany = 'manage company';
    case ViewAllRecords = 'view all records';
    case SeeFinancials = 'see financials';
    case ManagePipelines = 'manage pipelines';
}
