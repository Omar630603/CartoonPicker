<?php

namespace App\Http\Controllers;

use App\Models\Cartoon;
use App\Models\Criteria;
use App\Models\CriteriaIndicator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class CartoonController extends Controller
{
    public function index()
    {
        $cartoons = Cartoon::get(['cartoon_id', 'cartoon_name', 'cartoon_img']);
        $criteria_indicators = CriteriaIndicator::all();
        $criteria = Criteria::all();
        return view('welcome', compact('cartoons', 'criteria_indicators', 'criteria'));
    }
    function results($ranked)
    {
        $ids = implode(',', $ranked);
        $results = Cartoon::whereIntegerInRaw('cartoon_id', $ranked)
            ->orderByRaw(Cartoon::raw("FIELD(cartoon_id, $ids)"))
            ->get(['cartoon_id', 'cartoon_name', 'cartoon_img']);
        $cartoons = Cartoon::get(['cartoon_id', 'cartoon_name', 'cartoon_img']);
        $criteria_indicators = CriteriaIndicator::all();
        $criteria = Criteria::all();
        Session::flash('success', $results[0]->cartoon_name . ' is the highest-ranking among all your choices');
        return view('welcome', compact('cartoons', 'criteria_indicators', 'criteria', 'results'));
    }
    public function process(Request $request)
    {
        if ($request->has('cartoons')) {
            if (count($request->cartoons) < 2) {
                return redirect()->back()->with('error', 'Please select at least 2 cartoons');
            } else {
                $criteria = Criteria::all();
                for ($i = 1; $i <= count($criteria); $i++) {
                    if (!$request->has('criteria_indicator' . $i)) {
                        return redirect()->back()->with('error', 'Please select the weight of criteria ' . $criteria[$i - 1]->criteria_name);
                    }
                }
                // start working on Multi-Objective Optimization Method by Ratio Analysis method
                $cartoons = $request->cartoons;
                $weights = array();
                for ($i = 1; $i <= count($criteria); $i++) {
                    $weights[$criteria[$i - 1]->criteria_name] = $request->{'criteria_indicator' . $i};
                }
                // Normalize
                $normalized = $this->normalize($criteria, $cartoons);
                // Normalized * weights
                $weighted = $this->weighted($criteria, $cartoons, $normalized, $weights);
                // Sum of weighted and get final result
                $finalResult = $this->minMax($criteria, $cartoons, $weighted);
                // Send to results
                $ranked = array_keys($finalResult);

                return $this->results($ranked);
            }
        } else {
            return redirect()->back()->with('error', 'Please select at least 2 cartoons');
        }
    }
    public function normalize($criteria, $cartoons)
    {
        $scores = array();
        for ($j = 1; $j <= count($criteria); $j++) {
            for ($i = 0; $i < count($cartoons); $i++) {
                $a = Cartoon::where('cartoon_id', $cartoons[$i])->first();
                $value = pow($a->{$criteria[$j - 1]->criteria_name}, 2);
                $scores[$cartoons[$i]][$criteria[$j - 1]->criteria_name] = $value;
            }
        }

        for ($j = 1; $j <= count($criteria); $j++) {
            $value = array_sum(array_column($scores, $criteria[$j - 1]->criteria_name));
            $value = sqrt($value);
            for ($i = 0; $i < count($cartoons); $i++) {
                $a = Cartoon::where('cartoon_id', $cartoons[$i])->first();
                $scores[$cartoons[$i]][$criteria[$j - 1]->criteria_name] = $a->{$criteria[$j - 1]->criteria_name} / $value;
            }
        }
        return $scores;
    }
    public function weighted($criteria, $cartoons, $normalized, $weights)
    {
        $scores = $normalized;
        for ($j = 1; $j <= count($criteria); $j++) {
            for ($i = 0; $i < count($cartoons); $i++) {
                $value = $scores[$cartoons[$i]][$criteria[$j - 1]->criteria_name] * $weights[$criteria[$j - 1]->criteria_name];
                $scores[$cartoons[$i]][$criteria[$j - 1]->criteria_name] = $value;
            }
        }
        return $scores;
    }
    public function minMax($criteria, $cartoons, $weighted)
    {
        $scores = $weighted;
        $max = array();
        $min = array();

        for ($i = 0; $i < count($cartoons); $i++) {
            $valueMax = 0;
            $valueMin = 0;
            for ($j = 1; $j <= count($criteria); $j++) {
                if ($criteria[$j - 1]->criteria_type == 'benefit') {
                    $valueMax +=  $scores[$cartoons[$i]][$criteria[$j - 1]->criteria_name];
                } else if ($criteria[$j - 1]->criteria_type == 'cost') {
                    $valueMin +=  $scores[$cartoons[$i]][$criteria[$j - 1]->criteria_name];
                }
            }
            $max[$cartoons[$i]] = $valueMax;
            $min[$cartoons[$i]] = $valueMin;
        }
        return $this->ranking($cartoons, $max, $min);
    }
    public function ranking($cartoons, $max, $min)
    {
        $ranking = array();
        for ($i = 0; $i < count($cartoons); $i++) {
            $value = $max[$cartoons[$i]] - $min[$cartoons[$i]];
            $ranking[$cartoons[$i]] = $value;
        }
        return $this->sort($ranking);
    }
    public function sort($ranking)
    {
        arsort($ranking, SORT_NUMERIC);
        return $ranking;
    }
}
