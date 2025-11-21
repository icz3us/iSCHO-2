<?php
class PredictiveAnalytics {
	private $pdo;
	public function __construct($pdo) {
		$this->pdo = $pdo;
	}

	// Analyze scholarship trends by municipality
	public function analyzeScholarshipTrends() {
		$data = [];
		$generated_at = date('Y-m-d H:i:s');
		try {
			$stmt = $this->pdo->query("SELECT municipality, COUNT(*) as total_applicants, SUM(application_status = 'Approved') as total_approved FROM users_info GROUP BY municipality");
			while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
				$approval_rate = $row['total_applicants'] > 0 ? round(($row['total_approved'] / $row['total_applicants']) * 100, 2) : 0;
				// Simple trend: increasing if approval_rate > 50, decreasing if < 30, stable otherwise
				$trend = $approval_rate > 50 ? 'increasing' : ($approval_rate < 30 ? 'decreasing' : 'stable');
				$data[$row['municipality']] = [
					'total_applicants' => $row['total_applicants'],
					'total_approved' => $row['total_approved'],
					'approval_rate' => $approval_rate,
					'growth_trend' => $trend
				];
			}
			return [
				'success' => true,
				'data' => $data,
				'generated_at' => $generated_at
			];
		} catch (\Exception $e) {
			return ['success' => false, 'error' => 'Error analyzing trends: ' . $e->getMessage()];
		}
	}

	// Predict applicant success rates (overall and by factors)
	public function predictApplicantSuccess($applicantId = null) {
		$generated_at = date('Y-m-d H:i:s');
		try {
			$stmt = $this->pdo->query("SELECT application_status, municipality, barangay, sex, civil_status FROM users_info");
			$total = 0; $approved = 0;
			$factors = ['municipality'=>[], 'barangay'=>[], 'sex'=>[], 'civil_status'=>[]];
			while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
				$total++;
				if ($row['application_status'] === 'Approved') $approved++;
				foreach ($factors as $factor => &$group) {
					$key = $row[$factor] ?? 'Unknown';
					if (!isset($group[$key])) $group[$key] = ['total'=>0, 'approved'=>0];
					$group[$key]['total']++;
					if ($row['application_status'] === 'Approved') $group[$key]['approved']++;
				}
			}
			$success_factors = [];
			foreach ($factors as $factor => $group) {
				$success_factors[$factor] = [];
				foreach ($group as $key => $stats) {
					$rate = $stats['total'] > 0 ? round(($stats['approved']/$stats['total'])*100,2) : 0;
					$success_factors[$factor][$key] = [
						'success_rate' => $rate,
						'approved' => $stats['approved'],
						'total' => $stats['total']
					];
				}
			}
			$overall_success_rate = $total > 0 ? round(($approved/$total)*100,2) : 0;
			return [
				'success' => true,
				'overall_success_rate' => $overall_success_rate,
				'total_applicants' => $total,
				'approved_applicants' => $approved,
				'success_factors' => $success_factors,
				'generated_at' => $generated_at
			];
		} catch (\Exception $e) {
			return ['success' => false, 'error' => 'Error predicting success: ' . $e->getMessage()];
		}
	}

	// Generate recommendations based on trends and predictions
	public function generateRecommendations() {
		$generated_at = date('Y-m-d H:i:s');
		$recommendations = [];
		try {
			// Use trends and success rates to generate simple recommendations
			$trends = $this->analyzeScholarshipTrends();
			$predictions = $this->predictApplicantSuccess();
			if ($trends['success'] && $predictions['success']) {
				foreach ($trends['data'] as $municipality => $stats) {
					if ($stats['growth_trend'] === 'decreasing') {
						$recommendations[] = "Increase outreach in $municipality to boost approval rates.";
					} elseif ($stats['growth_trend'] === 'increasing') {
						$recommendations[] = "Maintain current strategies in $municipality for continued success.";
					}
				}
				foreach ($predictions['success_factors']['sex'] as $sex => $stats) {
					if ($stats['success_rate'] < 40) {
						$recommendations[] = "Provide additional support for $sex applicants.";
					}
				}
				if ($predictions['overall_success_rate'] < 50) {
					$recommendations[] = "Review application process to improve overall success rate.";
				}
			} else {
				$recommendations[] = "Unable to generate recommendations due to insufficient data.";
			}
			return [
				'success' => true,
				'recommendations' => $recommendations,
				'generated_at' => $generated_at
			];
		} catch (\Exception $e) {
			return ['success' => false, 'error' => 'Error generating recommendations: ' . $e->getMessage()];
		}
	}
}
