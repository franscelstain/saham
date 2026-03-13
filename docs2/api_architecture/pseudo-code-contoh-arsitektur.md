# Pseudo-code Contoh Arsitektur

Dokumen ini berisi pseudo-code singkat untuk menunjukkan bentuk implementasi yang sehat dan bentuk yang buruk. Pseudo-code ini tidak terikat bahasa atau framework tertentu.

## Contoh 1: Endpoint create order yang sehat

```text
function CreateOrderController.handle(request):
    input = CreateOrderRequestDto.fromTransport(request)
    result = CreateOrderService.execute(input)
    return CreateOrderResponseMapper.toPublicResponse(result)
```

```text
function CreateOrderService.execute(input):
    customer = CustomerRepository.findById(input.customerId)
    policyInput = OrderCreationPolicyInput.from(customer, input)
    decision = OrderCreationPolicy.evaluate(policyInput)

    if not decision.allowed:
        raise DomainError("ORDER_NOT_ALLOWED")

    orderData = OrderWriteData.from(input, decision)
    created = OrderRepository.create(orderData)

    return CreateOrderResultDto.from(created, decision)
```

```text
function OrderCreationPolicy.evaluate(input):
    if input.customerBlocked:
        return Decision.denied("CUSTOMER_BLOCKED")

    initialStatus = "pending"
    if input.totalAmount == 0:
        initialStatus = "confirmed"

    return Decision.allowed(initialStatus)
```

Ciri sehat:
- controller hanya boundary
- service hanya orchestration
- repository hanya persistence
- policy hanya rule domain
- response mapper hanya output publik

## Contoh 2: Endpoint create order yang buruk

```text
function CreateOrderController.handle(request):
    customer = DB.table("customers").find(request.customer_id)

    if customer.blocked:
        return json({ "error": "blocked" }, 400)

    status = "pending"
    if request.total_amount == 0:
        status = "confirmed"

    order = DB.table("orders").insert({
        customer_id: request.customer_id,
        total_amount: request.total_amount,
        status: status
    })

    return json(order, 200)
```

Masalah:
- query ada di controller
- rule domain ada di controller
- response publik dibentuk langsung tanpa kontrak
- persistence bocor ke transport

## Contoh 3: Repository sehat

```text
function OrderReadRepository.search(filter):
    return queryOrders()
        .whereStatus(filter.status)
        .whereCreatedBetween(filter.startDate, filter.endDate)
        .orderBy(filter.sortField, filter.sortDirection)
        .paginate(filter.page, filter.perPage)
        .mapToOrderPageResult()
```

## Contoh 4: Repository buruk

```text
function OrderRepository.search(request):
    return DB.table("orders")
        .where("status", request.status)
```

Masalah:
- repository menerima request framework mentah
- return shape tidak jelas
- query builder dilempar keluar

## Contoh 5: Domain compute sehat

```text
function InvestorEligibilityEvaluator.evaluate(input):
    if input.age < 18:
        return Eligibility.ineligible("UNDERAGE")

    if input.riskScore < input.minimumRiskScore:
        return Eligibility.ineligible("RISK_TOO_LOW")

    return Eligibility.eligible()
```

## Contoh 6: Domain compute buruk

```text
function InvestorEligibilityEvaluator.evaluate(request):
    investor = DB.table("investors").find(request.id)

    if now() > investor.expired_at:
        return responseJson({ "error": "expired" }, 403)

    return true
```

Masalah:
- domain query database
- domain baca request framework
- domain bentuk response publik
- domain ambil waktu sekarang secara liar

## Contoh 7: Batch processing sehat

```text
function ProcessSettlementService.execute(runInput):
    cursor = SettlementRepository.openCursor(runInput.criteria)

    for chunk in cursor.readInChunks(runInput.chunkSize):
        decisions = []

        for item in chunk:
            decision = SettlementTransitionRule.evaluate(item)
            decisions.append(decision)

        SettlementRepository.bulkUpdate(decisions)
        Logging.info("settlement_chunk_processed", {
            run_id: runInput.runId,
            chunk_size: len(chunk)
        })
```

## Contoh 8: Batch processing buruk

```text
function ProcessSettlementService.execute():
    rows = SettlementRepository.getAll()

    for row in rows:
        decision = SettlementTransitionRule.evaluate(row)
        SettlementRepository.updateSingle(row.id, decision)
```

Masalah:
- full load besar
- update item-per-item
- tidak ada run id
- tidak ada strategi chunking atau batching
