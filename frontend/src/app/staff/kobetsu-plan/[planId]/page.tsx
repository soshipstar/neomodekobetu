'use client';

import { useEffect } from 'react';
import { useRouter, useParams } from 'next/navigation';

export default function KobetsuPlanDetailRedirect() {
  const router = useRouter();
  const params = useParams();

  useEffect(() => {
    router.replace(`/staff/kobetsu-plan?plan_id=${params.planId}`);
  }, [router, params.planId]);

  return null;
}
