<div class="help-block">
    <a href="javascript:void(0)"
       onclick="$(this).closest('div').find('div').slideToggle();"
       class="btn btn-default"
       style="display: block; margin: 10px auto;">
        نمایش کدهای قابل استفاده در قالب بخش کاربری
    </a>
    {literal}
        <div style="display: none;">
            <table class="table">
                <tbody>
                    <tr>
                        <td>{from_y}</td>
                        <td>حداقل زمان تحویل سفارش توسط حامل به مشتری - سال</td>
                    </tr>
                    <tr>
                        <td>{from_m}</td>
                        <td>حداقل زمان تحویل سفارش توسط حامل به مشتری - ماه</td>
                    </tr>
                    <tr>
                        <td>{from_mm}</td>
                        <td>حداقل زمان تحویل سفارش توسط حامل به مشتری - نام ماه</td>
                    </tr>
                    <tr>
                        <td>{from_d}</td>
                        <td>حداقل زمان تحویل سفارش توسط حامل به مشتری - روز</td>
                    </tr>
                    <tr>
                        <td>{from_w}</td>
                        <td>حداقل زمان تحویل سفارش توسط حامل به مشتری - روز هفته</td>
                    </tr>
                    <tr>
                        <td colspan="2">
                            <hr>
                        </td>
                    </tr>
                    <tr>
                        <td>{to_y}</td>
                        <td>حداکثر زمان تحویل سفارش توسط حامل به مشتری - سال</td>
                    </tr>
                    <tr>
                        <td>{to_m}</td>
                        <td>حداکثر زمان تحویل سفارش توسط حامل به مشتری - ماه</td>
                    </tr>
                    <tr>
                        <td>{to_mm}</td>
                        <td>حداکثر زمان تحویل سفارش توسط حامل به مشتری - نام ماه</td>
                    </tr>
                    <tr>
                        <td>{to_d}</td>
                        <td>حداکثر زمان تحویل سفارش توسط حامل به مشتری - روز</td>
                    </tr>
                    <tr>
                        <td>{to_w}</td>
                        <td>حداکثر زمان تحویل سفارش توسط حامل به مشتری - روز هفته</td>
                    </tr>
                    <tr>
                        <td colspan="2">
                            <hr>
                        </td>
                    </tr>
                    <tr>
                        <td>{process_y}</td>
                        <td>زمان تحویل سفارش توسط شما به حامل - سال</td>
                    </tr>
                    <tr>
                        <td>{process_m}</td>
                        <td>زمان تحویل سفارش توسط شما به حامل - ماه</td>
                    </tr>
                    <tr>
                        <td>{process_mm}</td>
                        <td>زمان تحویل سفارش توسط شما به حامل - نام ماه</td>
                    </tr>
                    <tr>
                        <td>{process_d}</td>
                        <td>زمان تحویل سفارش توسط شما به حامل - روز</td>
                    </tr>
                    <tr>
                        <td>{process_w}</td>
                        <td>زمان تحویل سفارش توسط شما به حامل - روز هفته</td>
                    </tr>
                </tbody>
            </table>
        </div>
    {/literal}
</div>