<?php

namespace Sule\BankStatements;

/*
 * This file is part of the Sulaeman Bank Statements package.
 *
 * (c) Sulaeman <me@sulaeman.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class RecordNotFoundException extends \UnexpectedValueException {}
class UnderMaintenanceException extends \UnexpectedValueException {}
class LoginFailureException extends \UnexpectedValueException {}
class RequireExtendedProcessException extends \OutOfRangeException {}
