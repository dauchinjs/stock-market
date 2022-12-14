<?php

namespace App\Controllers\BuySellControllers;

use App\Models\UserStockModel;
use App\Redirect;
use App\Services\Stocks\TransferStocksService;
use App\Services\Database;
use App\Services\User\UserService;
use App\Template;
use App\Validation\User\LoginValidation;
use App\Validation\User\UserValidation;

class TransferController
{
    public function showForm(): Template
    {
        return new Template('transfer.twig');
    }

    public function execute(): Redirect
    {
        $transferStockService = new TransferStocksService();
        $stockValidation = $transferStockService->getStockBySymbol($_POST['symbol'], $_SESSION['auth_id']);
        if ($stockValidation === null) {
            $_SESSION['error']['noSymbolFound'] = true;

            return new Redirect('/transfer');
        }
        if($stockValidation->getAmount() < $_POST['amount']) {
            $_SESSION['error']['notEnoughStocks'] = true;

            return new Redirect('/transfer');
        }

        $userValidation = new UserValidation($_POST['email']);
        if ($userValidation->success() === false) {
            $_SESSION['error']['noUserFound'] = true;

            return new Redirect('/transfer');
        }

        $result = Database::getConnection()->executeQuery(
            'SELECT * FROM users WHERE id=?', [
            $_SESSION['auth_id']
        ]);

        $info = $result->fetchAssociative();

        $passwordValidation = new LoginValidation($info['email'], $_POST['password']);
        if ($passwordValidation->checkPassword() === false) {
            $_SESSION['error']['wrongPassword'] = true;

            return new Redirect('/transfer');
        }
        if($info['email'] === $_POST['email']) {
            $_SESSION['error']['sameUser'] = true;

            return new Redirect('/transfer');
        }

        if ($userValidation->success() === true) {
            $_SESSION['success']['transfer'] = true;
        }

        $userStock = $transferStockService->getStockBySymbol($_POST['symbol'], $_SESSION['auth_id']);
        $stockAmount = $userStock->getAmount() - $_POST['amount'];

        $userService = new UserService();
        $userId = $userService->getUserByEmail($_POST['email']);

        $stock = $transferStockService->getStock($_SESSION['auth_id'], $_POST['symbol']);
        $stock = new UserStockModel(
            $stock->getUserId(),
            $stock->getSymbol(),
            $stock->getAmount(),
            $stock->getPrice()
        );
        
        $transferStockService->updateStock($_SESSION['auth_id'], $stockAmount, $_POST['symbol']);
        $transferStockService->transferStock($stock, $userId['id'], $_POST['symbol'], $_POST['amount']);
        $transferStockService->transferTransaction($stock, $_SESSION['auth_id'], $_POST['amount'], $_POST['symbol']);

        return new Redirect('/transfer');
    }
}
