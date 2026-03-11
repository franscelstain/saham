# EOD Cutoff and Finalization Contract (LOCKED)

cutoff_time:
- session_close_time + grace minutes OR fixed platform cutoff

Cannot finalize SUCCESS before cutoff_time.
Finalize SUCCESS only if:
- bars published + indicators computed + eligibility built + gates pass