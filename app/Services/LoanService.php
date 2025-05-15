<?php

namespace App\Services;

use Exception;
use Carbon\Carbon;
use App\Models\Loan;
use App\Models\User;
use App\Models\ReceivedRepayment;
use App\Models\ScheduledRepayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;

class LoanService
{
    protected $totalAmount;

    public function __construct()
    {
        $this->totalAmount = 0;
    }
    /**
     * Create a Loan
     *
     * @param  User  $user
     * @param  int  $amount
     * @param  string  $currencyCode
     * @param  int  $terms
     * @param  string  $processedAt
     *
     * @return Loan
     */
    public function createLoan(User $user, int $amount, string $currencyCode, int $terms, string $processedAt): Loan
    {
        // Model::unguard();
        $loan =  Loan::create([
            'user_id' => $user->id,
            'amount' => $amount,
            'outstanding_amount' => $amount,
            'terms' => $terms,
            'currency_code' => $currencyCode,
            'processed_at' => Carbon::parse($processedAt)->format('Y-m-d'),
            'status' => Loan::STATUS_DUE,
        ]);

        for ($i = 1; $i <= $terms; $i++){
            $outstanding = $amount / 3;
            $formattedOutstanding = $i == 1 ? floor($outstanding) :  ceil($outstanding);
            $loan->scheduledRepayments()->create([
                'loan_id' => $loan->id,
                'amount' => $formattedOutstanding,
                'outstanding_amount' => $formattedOutstanding,
                'currency_code' => $currencyCode,
                'due_date' =>  Carbon::parse($processedAt)->addMonths($i)->format('Y-m-d'),
                'status' => ScheduledRepayment::STATUS_DUE,
            ]);
        }

        return $loan;
    }

    /**
     * Repay Scheduled Repayments for a Loan
     *
     * @param  Loan  $loan
     * @param  int  $amount
     * @param  string  $currencyCode
     * @param  string  $receivedAt
     *
     * @return Loan
     */
    public function repayLoan(Loan $loan, int $amount, string $currencyCode, string $receivedAt): Loan
    {

       try {
             DB::transaction(function () use($loan, $amount, $currencyCode, $receivedAt) {
                $this->totalAmount = $amount;
                $loan->scheduledRepayments()
                    ->where('status', ScheduledRepayment::STATUS_DUE)
                    ->orderBy('due_date')
                    ->get()
                    ->each(function(ScheduledRepayment $scheduledRepayment) use($amount) {
                        if ($this->totalAmount <= 0 ) {
                            return;
                        }
                        if ($this->totalAmount > 0 && $this->totalAmount >= $scheduledRepayment->amount) {
                            $out = $this->totalAmount - $scheduledRepayment->amount;
                            $this->totalAmount = $out;
                            $scheduledRepayment->update(['status' => ScheduledRepayment::STATUS_REPAID, 'outstanding_amount' => 0]);
                            return;
                        }
                        if ($this->totalAmount > 0 && $this->totalAmount < $scheduledRepayment->amount) {
                            $scheduledRepayment->update(['status' => ScheduledRepayment::STATUS_PARTIAL, 'outstanding_amount' => $this->totalAmount]);
                            $this->totalAmount = $this->totalAmount - $this->totalAmount;
                            return;
                        }
                    });

                $receivedRepayment =  ReceivedRepayment::create([
                    'loan_id' => $loan->id,
                    'amount' => $amount,
                    'currency_code' => $currencyCode,
                    'received_at' => Carbon::parse($receivedAt)->format('Y-m-d'),
                ]);

                $totalLoanOutstanding = $loan->amount - $loan->scheduledRepayments()->where('status', ScheduledRepayment::STATUS_REPAID)->sum('amount');
                $loan->update([
                    'status' => $totalLoanOutstanding > 0 ? Loan::STATUS_DUE: Loan::STATUS_REPAID,
                    'outstanding_amount'=> $totalLoanOutstanding - $loan->scheduledRepayments()->where('status', ScheduledRepayment::STATUS_PARTIAL)->sum('outstanding_amount'),
                ]);
                return $receivedRepayment->loan;
            });
       } catch(\Exception $e) {
            Log::error('[LOAN-SERVICE]', [
                'error' => $e->getMessage(),
            ]);
       }
       return $loan;
    }
}
