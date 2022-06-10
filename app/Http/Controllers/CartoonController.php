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
        $criterias = Criteria::all();
        return view('welcome', compact('cartoons', 'criteria_indicators', 'criterias'));
    }
    function results($ranked)
    {
        $ids = implode(',', $ranked);
        $results = Cartoon::whereIntegerInRaw('cartoon_id', $ranked)
            ->orderByRaw(Cartoon::raw("FIELD(cartoon_id, $ids)"))
            ->get(['cartoon_id', 'cartoon_name', 'cartoon_img']);
        $cartoons = Cartoon::get(['cartoon_id', 'cartoon_name', 'cartoon_img']);
        $criteria_indicators = CriteriaIndicator::all();
        $criterias = Criteria::all();
        Session::flash('success', $results[0]->cartoon_name . ' is the highest-ranking among all your choices');
        return view('welcome', compact('cartoons', 'criteria_indicators', 'criterias', 'results'));
    }
    public function process(Request $request)
    {
        if ($request->has('cartoons')) {
            if (count($request->cartoons) < 2) {
                return redirect()->back()->with('error', 'Please select at least 2 cartoons');
            } else {
                $criterias = Criteria::all();
                for ($i = 1; $i <= count($criterias); $i++) {
                    if (!$request->has('criteria_indicator' . $i)) {
                        return redirect()->back()->with('error', 'Please select the weghit of cretira ' . $criterias[$i - 1]->criteria_name);
                    }
                }
                // start working on moora method
                $cartoons = $request->cartoons;
                $weights = array();
                for ($i = 1; $i <= count($criterias); $i++) {
                    $weights[$criterias[$i - 1]->criteria_name] = $request->{'criteria_indicator' . $i};
                }
                // noramlize
                $noramlized = $this->normalize($criterias, $cartoons);
                // normalized * weights
                $weighted = $this->weighted($criterias, $cartoons, $noramlized, $weights);
                // sum of weighted and get final result
                $finalResult = $this->minMax($criterias, $cartoons, $weighted);
                //send to results
                $ranked = array_keys($finalResult);

                return $this->results($ranked);
            }
        } else {
            return redirect()->back()->with('error', 'Please select at least 2 cartoons');
        }
    }
    public function normalize($criterias, $cartoons)
    {
        $scores = array();
        for ($j = 1; $j <= count($criterias); $j++) {
            for ($i = 0; $i < count($cartoons); $i++) {
                $a = Cartoon::where('cartoon_id', $cartoons[$i])->first();
                $value = pow($a->{$criterias[$j - 1]->criteria_name}, 2);
                $scores[$cartoons[$i]][$criterias[$j - 1]->criteria_name] = $value;
            }
        }

        for ($j = 1; $j <= count($criterias); $j++) {
            $value = array_sum(array_column($scores, $criterias[$j - 1]->criteria_name));
            $value = sqrt($value);
            for ($i = 0; $i < count($cartoons); $i++) {
                $a = Cartoon::where('cartoon_id', $cartoons[$i])->first();
                $scores[$cartoons[$i]][$criterias[$j - 1]->criteria_name] = $a->{$criterias[$j - 1]->criteria_name} / $value;
            }
        }
        return $scores;
    }
    public function weighted($criterias, $cartoons, $noramlized, $weights)
    {
        $scores = $noramlized;
        for ($j = 1; $j <= count($criterias); $j++) {
            for ($i = 0; $i < count($cartoons); $i++) {
                $value = $scores[$cartoons[$i]][$criterias[$j - 1]->criteria_name] * $weights[$criterias[$j - 1]->criteria_name];
                $scores[$cartoons[$i]][$criterias[$j - 1]->criteria_name] = $value;
            }
        }
        return $scores;
    }
    public function minMax($criterias, $cartoons, $weighted)
    {
        $scores = $weighted;
        $max = array();
        $min = array();

        for ($i = 0; $i < count($cartoons); $i++) {
            $valueMax = 0;
            $valueMin = 0;
            for ($j = 1; $j <= count($criterias); $j++) {
                if ($criterias[$j - 1]->criteria_type == 'benefit') {
                    $valueMax +=  $scores[$cartoons[$i]][$criterias[$j - 1]->criteria_name];
                } else if ($criterias[$j - 1]->criteria_type == 'cost') {
                    $valueMin +=  $scores[$cartoons[$i]][$criterias[$j - 1]->criteria_name];
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
